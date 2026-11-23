<?php

namespace App\Services;

use App\Exceptions\StokException;
use App\Models\Barang;
use App\Models\BatchStok;
use App\Models\MutasiStok;
use App\Models\User;
use App\Support\Aktivitas;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aturan stok inti (BR-01 s/d BR-05):
 * - setiap perubahan saldo dilakukan dalam DB::transaction + lockForUpdate (NFR-02);
 * - pengeluaran memakai alokasi FEFO (AB-02, FR-11);
 * - setiap batch yang dialokasi menghasilkan satu baris mutasi (5.9 A);
 * - batch kedaluwarsa / Ditangani tidak bisa keluar normal (FR-07, BR-03);
 * - urutan kunci konsisten: baris barang selalu dikunci lebih dahulu
 *   untuk mencegah deadlock antaroperasi.
 */
final class StockTransactionService
{
    /**
     * Stok masuk (FR-06): menambah batch yang sudah ada, atau membuat batch baru.
     *
     * @param  array{barang_id?: int, batch_id?: int, jumlah?: int, nomor_batch?: string,
     *               tanggal_kedaluwarsa?: string, catatan?: string, waktu?: string}  $params
     * @return array{
     *     transaksi: array<int, array<string, mixed>>,
     *     batch: array<string, mixed>,
     *     barang: array<string, mixed>
     * }
     */
    public function masuk(User $user, array $params): array
    {
        $jumlah = (int) ($params['jumlah'] ?? 0);
        $catatan = $params['catatan'] ?? null;
        $waktu = Carbon::parse($params['waktu'] ?? now());

        $hasil = DB::transaction(function () use ($user, $params, $jumlah, $catatan, $waktu) {
            $barang = Barang::query()->lockForUpdate()->find($params['barang_id'] ?? null)
                ?? throw StokException::field('barang_id', 'Barang tidak ditemukan atau sudah terhapus.', 'Barang tidak ditemukan');

            if (empty($params['batch_id'])) {
                $batch = $this->buatBatchBaru($barang, $jumlah, $params, $user);
            } else {
                $batch = $barang->batches()->lockForUpdate()->find($params['batch_id'])
                    ?? throw StokException::field('batch_id', 'Batch tidak ditemukan pada barang ini.', 'Batch tidak ditemukan');

                if (in_array($batch->status, ['Kedaluwarsa', 'Ditangani'], true)) {
                    throw StokException::field('batch_id', "Batch {$batch->nomor_batch} berstatus {$batch->status}; tidak dapat menerima stok masuk.", 'Batch tidak dapat menerima stok');
                }

                if ($batch->tanggal_kedaluwarsa->lessThan(today())) {
                    throw StokException::field('batch_id', "Batch {$batch->nomor_batch} sudah melewati tanggal kedaluwarsa ({$batch->tanggal_kedaluwarsa->toDateString()}); tangani batch ini, jangan isi ulang (FR-06).", 'Batch tidak dapat menerima stok');
                }

                $batch->jumlah_awal += $jumlah;
                $batch->jumlah_sisa += $jumlah;
                $batch->save();
            }

            $batch->setRelation('barang', $barang);

            $transaksi = $this->catatMutasi($batch, 'masuk', $jumlah, $catatan, $user, $waktu);

            return [
                'transaksi' => $transaksi,
                'batch' => $batch,
                'barang' => $barang,
            ];
        });

        $batch = $hasil['batch'];

        Aktivitas::catat(
            $user, 'tambah', 'batch_stok', $batch->id,
            "Stok masuk {$jumlah} {$hasil['barang']->satuan} ke batch {$batch->nomor_batch} ({$hasil['barang']->nama})"
        );

        return [
            'transaksi' => [$hasil['transaksi']],
            'batch' => $batch->withoutRelations()->toArray(),
            'barang' => $hasil['barang']->only(['id', 'kode_sku', 'nama', 'kategori', 'satuan', 'stok_minimum']),
        ];
    }

    /**
     * Stok keluar FEFO (FR-07, FR-11): alokasi dari batch kedaluwarsa terdekat dahulu;
     * setiap batch teralokasi menghasilkan satu baris mutasi (5.9 A).
     *
     * @param  array{barang_id?: int, jumlah?: int, batch_id?: int, catatan?: string, waktu?: string}  $params
     * @return array{
     *     transaksi: array<int, array<string, mixed>>,
     *     barang: array<string, mixed>,
     *     sisa_layak_jual: int
     * }
     */
    public function keluar(User $user, array $params): array
    {
        $jumlah = (int) ($params['jumlah'] ?? 0);
        $catatan = $params['catatan'] ?? null;
        $waktu = Carbon::parse($params['waktu'] ?? now());

        $hasil = DB::transaction(function () use ($user, $params, $jumlah, $catatan, $waktu) {
            $barang = Barang::query()->lockForUpdate()->find($params['barang_id'] ?? null)
                ?? throw StokException::field('barang_id', 'Barang tidak ditemukan atau sudah terhapus.', 'Barang tidak ditemukan');

            $alokasi = $this->alokasikan($barang, $params['batch_id'] ?? null, $jumlah);

            $transaksi = [];
            foreach ($alokasi as $potongan) {
                $batch = $potongan['batch'];
                $batch->jumlah_sisa -= $potongan['ambil'];
                $batch->save();

                $transaksi[] = $this->catatMutasi($batch, 'keluar', $potongan['ambil'], $catatan, $user, $waktu);
            }

            $sisaLayakJual = (int) $barang->batches()->layakJual()->sum('jumlah_sisa');

            return [
                'transaksi' => $transaksi,
                'barang' => $barang,
                'sisa_layak_jual' => $sisaLayakJual,
            ];
        });

        $barang = $hasil['barang'];
        $jumlahBatch = count($hasil['transaksi']);
        $batchIdUtama = $hasil['transaksi'][0]['batch_id'] ?? $barang->id;

        Aktivitas::catat(
            $user, 'pengeluaran_stok', 'batch_stok', (int) $batchIdUtama,
            "Keluar {$jumlah} {$barang->satuan} {$barang->nama} secara FEFO dari {$jumlahBatch} batch"
        );

        // Peringatan stok menipis diperbarui ketika transaksi mengubah kondisi stok (FR-13, BR-02)
        ExpiryMonitoringService::notifikasiStokMenipisSekarang();

        return [
            'transaksi' => $hasil['transaksi'],
            'barang' => $barang->only(['id', 'kode_sku', 'nama', 'satuan', 'stok_minimum']),
            'sisa_layak_jual' => $hasil['sisa_layak_jual'],
        ];
    }

    /**
     * Penyesuaian (FR-13): koreksi saldo batch dengan selisih dan alasan.
     *
     * @param  array{batch_id?: int, jumlah?: int, catatan?: string, waktu?: string}  $params
     * @return array{transaksi: array<int, array<string, mixed>>, batch: array<string, mixed>}
     */
    public function penyesuaian(User $user, array $params): array
    {
        $hasil = DB::transaction(function () use ($user, $params) {
            $batchId = $params['batch_id'] ?? null;

            $barangId = BatchStok::query()->where('id', $batchId)->value('barang_id')
                ?? throw StokException::field('batch_id', 'Batch tidak ditemukan.', 'Batch tidak ditemukan');

            // Urutan kunci konsisten: barang lebih dahulu, lalu batch
            Barang::query()->lockForUpdate()->find($barangId);

            $batch = BatchStok::query()->lockForUpdate()->find($batchId)
                ?? throw StokException::field('batch_id', 'Batch tidak ditemukan.', 'Batch tidak ditemukan');

            $selisih = (int) ($params['jumlah'] ?? 0);
            $sisaBaru = $batch->jumlah_sisa + $selisih;

            if ($sisaBaru < 0) {
                throw StokException::field('jumlah', "Penyesuaian tidak boleh membuat jumlah sisa negatif; saldo saat ini {$batch->jumlah_sisa}.", 'Penyesuaian tidak valid');
            }

            if ($sisaBaru > $batch->jumlah_awal) {
                throw StokException::field('jumlah', "Penyesuaian tidak boleh melebihi jumlah awal batch ({$batch->jumlah_awal}).", 'Penyesuaian tidak valid');
            }

            $batch->jumlah_sisa = $sisaBaru;
            $batch->save();

            $batch->setRelation('barang', Barang::find($barangId));

            $transaksi = $this->catatMutasi(
                $batch, 'penyesuaian', $selisih, $params['catatan'] ?? null, $user,
                Carbon::parse($params['waktu'] ?? now())
            );

            return ['transaksi' => $transaksi, 'batch' => $batch];
        });

        $batch = $hasil['batch'];

        Aktivitas::catat(
            $user, 'ubah', 'batch_stok', $batch->id,
            "Penyesuaian batch {$batch->nomor_batch}: selisih {$hasil['transaksi']['jumlah']} — {$params['catatan']}"
        );

        ExpiryMonitoringService::notifikasiStokMenipisSekarang();

        return [
            'transaksi' => [$hasil['transaksi']],
            'batch' => $batch->withoutRelations()->toArray(),
        ];
    }

    /**
     * Penanganan kedaluwarsa (FR-12): mengeluarkan stok batch kedaluwarsa
     * (penuh atau bertahap), lalu menandai status Ditangani saat sisa habis.
     *
     * @param  array{batch_id?: int, status_penanganan?: string, jumlah?: int,
     *               catatan_penanganan?: string, waktu?: string}  $params
     * @return array{
     *     transaksi: array<int, array<string, mixed>>,
     *     batch: array<string, mixed>,
     *     selesai: bool
     * }
     */
    public function penanganan(User $user, array $params): array
    {
        $hasil = DB::transaction(function () use ($user, $params) {
            $batchId = $params['batch_id'] ?? null;

            $barangId = BatchStok::query()->where('id', $batchId)->value('barang_id')
                ?? throw StokException::field('batch_id', 'Batch tidak ditemukan.', 'Batch tidak ditemukan');

            Barang::query()->lockForUpdate()->find($barangId);

            $batch = BatchStok::query()->lockForUpdate()->find($batchId)
                ?? throw StokException::field('batch_id', 'Batch tidak ditemukan.', 'Batch tidak ditemukan');

            $barang = Barang::find($barangId);

            if ($batch->jumlah_sisa <= 0) {
                throw StokException::field('batch_id', 'Batch ini tidak memiliki stok tersisa untuk ditangani.', 'Batch tidak memiliki sisa stok');
            }

            $jumlah = isset($params['jumlah'])
                ? (int) $params['jumlah']
                : $batch->jumlah_sisa;

            if ($jumlah <= 0) {
                throw StokException::field('jumlah', 'Jumlah penanganan harus lebih besar dari 0 (FR-12).', 'Penanganan ditolak');
            }

            if ($jumlah > $batch->jumlah_sisa) {
                throw StokException::field('jumlah', "Jumlah penanganan melebihi stok tersisa ({$batch->jumlah_sisa}) (FR-12).", 'Penanganan ditolak');
            }

            $statusPenanganan = $params['status_penanganan'] ?? 'Lainnya';
            $catatanPenanganan = $params['catatan_penanganan'] ?? null;

            if ($statusPenanganan === 'Lainnya' && ($catatanPenanganan === null || trim($catatanPenanganan) === '')) {
                throw StokException::field('catatan_penanganan', 'Catatan penanganan wajib diisi bila tindakan adalah Lainnya (FR-12).', 'Catatan wajib');
            }

            $batch->jumlah_sisa -= $jumlah;
            $selesai = $batch->jumlah_sisa === 0;

            if ($selesai) {
                $batch->status = 'Ditangani';
            }

            $batch->status_penanganan = $statusPenanganan;

            if ($catatanPenanganan !== null && trim($catatanPenanganan) !== '') {
                $batch->catatan_penanganan = $catatanPenanganan;
            }

            $batch->save();

            $batch->setRelation('barang', $barang);

            $transaksi = $this->catatMutasi(
                $batch, 'penanganan', $jumlah, $catatanPenanganan ?? $statusPenanganan, $user,
                Carbon::parse($params['waktu'] ?? now())
            );

            return ['transaksi' => $transaksi, 'batch' => $batch, 'selesai' => $selesai];
        });

        $batch = $hasil['batch'];

        Aktivitas::catat(
            $user, 'penanganan_kedaluwarsa', 'batch_stok', $batch->id,
            "Batch {$batch->nomor_batch} ({$batch->barang->nama}) {$batch->status_penanganan}: {$hasil['transaksi']['jumlah']}"
        );

        return [
            'transaksi' => [$hasil['transaksi']],
            'batch' => $batch->withoutRelations()->toArray(),
            'selesai' => $hasil['selesai'],
        ];
    }

    /**
     * Alokasi FEFO (BR-04): kedaluwarsa terdekat dahulu, lalu dibuat lebih awal,
     * dengan menolak transaksi bila stok layak jual tidak cukup.
     *
     * @return array<int, array{batch: BatchStok, ambil: int}>
     */
    private function alokasikan(Barang $barang, ?int $batchTertentu, int $jumlah): array
    {
        if ($jumlah <= 0) {
            throw StokException::field('jumlah', 'Jumlah keluar harus lebih besar dari 0 (FR-07).', 'Transaksi ditolak');
        }

        if ($batchTertentu !== null) {
            $batch = $barang->batches()->layakJual()->lockForUpdate()->find($batchTertentu)
                ?? throw StokException::field('batch_id', 'Batch tidak ditemukan, tidak layak dijual, atau bukan milik barang ini (FR-07).', 'Batch tidak layak dijual');

            if ($batch->jumlah_sisa < $jumlah) {
                throw StokException::field('jumlah', "Batch {$batch->nomor_batch} hanya tersedia {$batch->jumlah_sisa}, tidak mencukupi untuk {$jumlah}.", 'Stok tidak mencukupi');
            }

            return [['batch' => $batch, 'ambil' => $jumlah]];
        }

        $kandidat = $barang->batches()->layakJual()->fefo()->lockForUpdate()->get();

        $alokasi = [];
        $sisaKebutuhan = $jumlah;

        foreach ($kandidat as $batch) {
            $ambil = min($batch->jumlah_sisa, $sisaKebutuhan);

            if ($ambil <= 0) {
                continue;
            }

            $alokasi[] = ['batch' => $batch, 'ambil' => $ambil];
            $sisaKebutuhan -= $ambil;

            if ($sisaKebutuhan === 0) {
                break;
            }
        }

        if ($sisaKebutuhan > 0) {
            $tersedia = $jumlah - $sisaKebutuhan;
            throw StokException::field('jumlah', "Stok layak jual tidak mencukupi: butuh {$jumlah}, tersedia {$tersedia}.", 'Stok tidak mencukupi');
        }

        return $alokasi;
    }

    /**
     * Membuat batch baru untuk penerimaan (FR-06, FR-05).
     */
    private function buatBatchBaru(Barang $barang, int $jumlah, array $params, User $user): BatchStok
    {
        if (empty($params['nomor_batch'])) {
            throw ValidationException::withMessages(['nomor_batch' => 'Nomor batch wajib diisi saat menambahkan batch baru (FR-05).']);
        }

        if (empty($params['tanggal_kedaluwarsa'])) {
            throw ValidationException::withMessages(['tanggal_kedaluwarsa' => 'Tanggal kedaluwarsa wajib diisi saat menambahkan batch baru (FR-05).']);
        }

        $tanggal = Carbon::parse($params['tanggal_kedaluwarsa']);

        if ($tanggal->lessThan(today())) {
            throw ValidationException::withMessages(['tanggal_kedaluwarsa' => 'Tanggal kedaluwarsa tidak boleh di masa lalu (FR-05).']);
        }

        try {
            $batch = $user->batchDibuat()->create([
                'barang_id' => $barang->id,
                'nomor_batch' => trim((string) $params['nomor_batch']),
                'jumlah_awal' => $jumlah,
                'jumlah_sisa' => $jumlah,
                'tanggal_kedaluwarsa' => $tanggal->toDateString(),
            ]);
            $batch->refresh();
        } catch (QueryException $e) {
            // Bentrok dengan uq_batch_nomor_aktif (nomor_batch unik di antara batch aktif)
            if ($e->getMessage() !== '' && str_contains($e->getMessage(), 'uq_batch_nomor_aktif')) {
                throw StokException::field('nomor_batch', "Nomor batch {$params['nomor_batch']} sudah dipakai pada barang ini (AB-07).", 'Nomor batch duplikat');
            }

            throw $e;
        }

        return $batch->load('barang');
    }

    /**
     * Catat baris mutasi (AB-10) dan kembalikan sebagai array serial.
     *
     * @return array<string, mixed>
     */
    private function catatMutasi(
        BatchStok $batch,
        string $jenis,
        int $jumlah,
        ?string $catatan,
        User $user,
        Carbon $waktu,
    ): array {
        return MutasiStok::create([
            'batch_id' => $batch->id,
            'jenis' => $jenis,
            'jumlah' => $jumlah,
            'catatan' => $catatan,
            'user_id' => $user->id,
            'waktu' => $waktu,
        ])->toArray();
    }
}
