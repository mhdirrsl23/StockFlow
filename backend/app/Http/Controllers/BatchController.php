<?php

namespace App\Http\Controllers;

use App\Http\Requests\BatchRequest;
use App\Models\Barang;
use App\Models\BatchStok;
use App\Models\MutasiStok;
use App\Models\Notifikasi;
use App\Support\Aktivitas;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class BatchController extends Controller
{
    /**
     * FR-12 halaman batch: daftar batch global dengan filter barang & status;
     * urutan bawaan FEFO (AB-02).
     */
    public function index(Request $request): mixed
    {
        $query = BatchStok::query()
            ->with('barang:id,kode_sku,nama,satuan,stok_minimum')
            ->fefo();

        if ($barangId = $request->query('barang_id')) {
            $query->where('barang_id', (int) $barangId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($dari = $request->query('dari')) {
            $query->whereDate('tanggal_kedaluwarsa', '>=', $dari);
        }

        if ($sampai = $request->query('sampai')) {
            $query->whereDate('tanggal_kedaluwarsa', '<=', $sampai);
        }

        $paginasi = $query->paginate(min(100, max(1, (int) ($request->query('per_page', 20)))))->appends($request->query());

        $sisaHari = fn (BatchStok $batch) => today()->diffInDays(Carbon::parse($batch->tanggal_kedaluwarsa), false);

        $items = collect($paginasi->items())->map(fn (BatchStok $batch) => [
            'id' => $batch->id,
            'barang_id' => $batch->barang_id,
            'kode_sku' => $batch->barang->kode_sku,
            'nama_barang' => $batch->barang->nama,
            'nomor_batch' => $batch->nomor_batch,
            'jumlah_awal' => $batch->jumlah_awal,
            'jumlah_sisa' => $batch->jumlah_sisa,
            'tanggal_kedaluwarsa' => $batch->tanggal_kedaluwarsa->toDateString(),
            'status' => $batch->status,
            'status_penanganan' => $batch->status_penanganan,
            'sisa_hari' => $sisaHari($batch),
        ]);

        return ApiResponse::ok([
            'items' => $items,
            'pagination' => [
                'halaman' => $paginasi->currentPage(),
                'per_halaman' => $paginasi->perPage(),
                'total_baris' => $paginasi->total(),
                'halaman_terakhir' => $paginasi->lastPage(),
            ],
        ], 'Daftar batch ditemukan');
    }

    /**
     * FR-12: batch milik barang (urutan FEFO), mencakup rekomendasi layak jual.
     */
    public function untukBarang(Barang $barang): mixed
    {
        $batches = $barang->batches()->fefo()->get();
        $sisaHari = fn (BatchStok $batch) => today()->diffInDays(Carbon::parse($batch->tanggal_kedaluwarsa), false);

        $items = $batches->map(fn (BatchStok $batch) => $this->bentuk($batch) + ['sisa_hari' => $sisaHari($batch)]);

        $rekomendasi = $batches->filter(fn (BatchStok $batch) => $batch->status !== 'Ditangani' && $batch->jumlah_sisa > 0)
            ->first(fn (BatchStok $batch) => $batch->status !== 'Kedaluwarsa' && $batch->jumlah_sisa > 0);

        return ApiResponse::ok([
            'barang' => [
                'id' => $barang->id,
                'kode_sku' => $barang->kode_sku,
                'nama' => $barang->nama,
                'kategori' => $barang->kategori,
                'satuan' => $barang->satuan,
                'stok_minimum' => $barang->stok_minimum,
            ],
            'items' => $items,
            'rekomendasi_fefo' => $rekomendasi ? $rekomendasi->id : null,
        ], 'Daftar batch barang ditemukan');
    }

    /**
     * FR-05: store batch baru = mutasi masuk stok awal (BR-01, 4.2 alur).
     */
    public function store(BatchRequest $request, Barang $barang): mixed
    {
        $hasil = app(\App\Services\StockTransactionService::class)->masuk($request->user(), [
            'barang_id' => $barang->id,
            'jumlah' => (int) $request->input('jumlah_awal'),
            'nomor_batch' => $request->input('nomor_batch'),
            'tanggal_kedaluwarsa' => $request->input('tanggal_kedaluwarsa'),
            'catatan' => 'Stok awal batch',
            'waktu' => now()->toDateTimeString(),
        ]);

        return ApiResponse::created($hasil['batch'], 'Batch berhasil dibuat dan stok awal tercatat sebagai transaksi masuk (FR-05)');
    }

    /**
     * FR-05: ubah karakteristik batch yang boleh diubah; tidak ada jalur
     * pengubahan stok langsung — jumlah hanya berubah lewat transaksi (BR-01).
     */
    public function update(Request $request, BatchStok $batch): mixed
    {
        $data = $request->validate([
            'nomor_batch' => ['sometimes', 'string', 'max:100'],
            'tanggal_kedaluwarsa' => ['sometimes', 'date'],
        ]);

        return DB::transaction(function () use ($data, $batch, $request) {
            if (isset($data['nomor_batch']) && $data['nomor_batch'] !== $batch->nomor_batch
                && $batch->barang->batches()->where('nomor_batch', $data['nomor_batch'])->where('id', '!=', $batch->id)->exists()) {
                return ApiResponse::fail('Nomor batch sudah dipakai pada barang ini (AB-07).', 422, [
                    'nomor_batch' => ['Nomor batch sudah dipakai pada barang ini.'],
                ]);
            }

            if (isset($data['tanggal_kedaluwarsa'])) {
                $tanggal = Carbon::parse($data['tanggal_kedaluwarsa']);
                $tanggalMasuk = MutasiStok::query()->where('batch_id', $batch->id)->oldest('waktu')->value('waktu');

                if ($tanggalMasuk !== null && $tanggal->lessThan(Carbon::parse($tanggalMasuk)->startOfDay())) {
                    return ApiResponse::fail('Tanggal kedaluwarsa tidak boleh sebelum tanggal masuk pertama (FR-05).', 422, [
                        'tanggal_kedaluwarsa' => ['Tanggal kedaluwarsa tidak boleh sebelum tanggal masuk batch.'],
                    ]);
                }

                $data['tanggal_kedaluwarsa'] = $tanggal->toDateString();
            }

            $batch->fill($data);
            $batch->save();

            Aktivitas::catat($request->user(), 'ubah', 'batch_stok', $batch->id, 'Ubah batch '.$batch->nomor_batch);

            return ApiResponse::ok($this->bentuk($batch), 'Batch berhasil diperbarui');
        });
    }

    /**
     * AB-08 + BR-07: batch dengan riwayat mutasi dilindungi hapus permanen,
     * gunakan soft delete dengan otorisasi staf-milik-sendiri.
     */
    public function destroy(Request $request, BatchStok $batch): mixed
    {
        $user = $request->user();

        if (! $user->isPemilik() && $batch->created_by !== $user->id) {
            return ApiResponse::fail('Akses ditolak: staf hanya dapat menghapus batch yang ia buat sendiri (AB-08).', 403);
        }

        return DB::transaction(function () use ($batch, $user) {
            $faithful = $batch->mutasi()->exists();

            $batch->deleted_by = $user->id;
            $batch->delete();

            Notifikasi::query()->where('batch_id', $batch->id)->delete();

            Aktivitas::catat(
                $user,
                'hapus',
                'batch_stok',
                $batch->id,
                'Hapus batch '.$batch->nomor_batch.($faithful ? ' — riwayat mutasi tetap tersimpan (BR-07)' : ''),
            );

            return ApiResponse::ok(null, 'Batch dihapus — data tetap tersimpan untuk keterlacakan (BR-07)');
        });
    }

    /**
     * FR-18: hapus massal batch; hasil per item.
     */
    public function bulkDelete(Request $request): mixed
    {
        $ids = $request->input('ids', []);

        if (! is_array($ids) || $ids === []) {
            return ApiResponse::fail('Hapus massal membutuhkan daftar id batch (FR-18).', 422, [
                'ids' => ['Daftar id batch wajib diisi.'],
            ]);
        }

        $user = $request->user();
        $ids = array_map('intval', $ids);

        return DB::transaction(function () use ($user, $ids) {
            $hasil = [];

            foreach ($ids as $id) {
                $batch = BatchStok::query()->find($id);

                if ($batch === null) {
                    $hasil[] = ['id' => $id, 'berhasil' => false, 'pesan' => 'Batch tidak ditemukan'];
                    continue;
                }

                if (! $user->isPemilik() && $batch->created_by !== $user->id) {
                    $hasil[] = [
                        'id' => $id,
                        'berhasil' => false,
                        'pesan' => 'Akses ditolak: staf hanya menghapus batch miliknya sendiri (AB-08)',
                    ];
                    continue;
                }

                $batch->deleted_by = $user->id;
                $batch->delete();

                Notifikasi::query()->where('batch_id', $id)->delete();

                Aktivitas::catat($user, 'hapus_massal', 'batch_stok', $id, 'Hapus massal batch '.$batch->nomor_batch);

                $hasil[] = ['id' => $id, 'berhasil' => true, 'pesan' => 'Hapus berhasil'];
            }

            $berhasil = count(array_filter($hasil, fn ($r) => $r['berhasil']));

            return ApiResponse::ok([
                'total' => count($hasil),
                'berhasil' => $berhasil,
                'gagal' => count($hasil) - $berhasil,
                'hasil' => $hasil,
            ], 'Hapus massal batch diproses');
        });
    }

    /**
     * Detail batch (FR-12) dengan mutasi terbaru dan lampirannya (Q-05).
     */
    public function show(BatchStok $batch): mixed
    {
        $batch->load(['barang:id,kode_sku,nama,satuan', 'lampiran']);
        $mutasiTerbaru = $batch->mutasi()->limit(20)->get();

        return ApiResponse::ok($this->bentuk($batch) + [
            'mutasi_terbaru' => $mutasiTerbaru,
            'lampiran' => $batch->lampiran->toArray(),
        ], 'Batch ditemukan');
    }

    private function bentuk(BatchStok $batch): array
    {
        return [
            'id' => $batch->id,
            'barang_id' => $batch->barang_id,
            'kode_sku' => $batch->barang->kode_sku ?? null,
            'nama_barang' => $batch->barang->nama ?? null,
            'nomor_batch' => $batch->nomor_batch,
            'jumlah_awal' => $batch->jumlah_awal,
            'jumlah_sisa' => $batch->jumlah_sisa,
            'tanggal_kedaluwarsa' => $batch->tanggal_kedaluwarsa?->toDateString(),
            'status' => $batch->status,
            'status_penanganan' => $batch->status_penanganan,
            'catatan_penanganan' => $batch->catatan_penanganan,
            'created_at' => $batch->created_at?->toDateTimeString(),
            'updated_at' => $batch->updated_at?->toDateTimeString(),
        ];
    }
}
