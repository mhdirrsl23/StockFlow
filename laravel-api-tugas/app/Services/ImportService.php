<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\Impor;
use App\Models\Pengaturan;
use App\Models\User;
use App\Support\Aktivitas;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Impor barang Excel dua langkah (FR-15, BR-06):
 * menganalisis() (pratinjau) memvalidasi kolom + baris lalu menyimpan draf dengan
 * token konfirmasi berlaku 30 menit; simpan() (konfirmasi) hanya menuliskan baris
 * valid — baris invalid tidak pernah masuk secara diam-diam (BR-06).
 */
final class ImportService
{
    /**
     * Kolom wajib berkas impor.
     */
    private const HEADER_WAJIB = ['kode_sku', 'nama', 'kategori', 'satuan', 'stok_minimum'];

    private const KATEGORI = ['Makanan', 'Minuman', 'Obat', 'Kebutuhan Rumah', 'Lainnya'];

    /**
     * Analisis berkas dan terbitkan draf impor + token konfirmasi.
     *
     * @return array{id: int, token: string, kedaluwarsa_token: string|null, nama_berkas: string,
     *              jumlah_baris: int, valid: int, gagal: int,
     *              baris_valid: list<array<string, mixed>>, kesalahan: array<int, list<string>>}
     */
    public function menganalisis(User $user, UploadedFile $berkas): array
    {
        $this->pastikanUkuran($berkas);

        [$headerIndeks, $semuaBaris] = $this->bacaBerkas($berkas);

        $barisValid = [];
        $kesalahan = [];

        foreach ($semuaBaris as $nomorBaris => $seluruhSel) {
            $data = $this->tarikData($seluruhSel, $headerIndeks);
            $masalah = $this->validasiBaris($data);

            if ($masalah !== []) {
                $kesalahan[$nomorBaris] = $masalah;
                continue;
            }

            $barisValid[] = ['baris' => $nomorBaris] + $data;
        }

        // Duplikasi kode di dalam berkas: baris ulangan ditolak agar
        // perbaikan berkas diminta sebelum impor (BR-06).
        $lihatKode = [];
        foreach ($barisValid as $indeks => $item) {
            $kode = $item['kode_sku'];

            if (isset($lihatKode[$kode])) {
                unset($barisValid[$indeks]);
                $kesalahan[$item['baris']] = ["Kode SKU {$kode} duplikat di dalam berkas; perbaiki sebelum impor (BR-06)."];
                continue;
            }

            $lihatKode[$kode] = true;
        }

        $barisValid = array_values($barisValid);

        if ($barisValid === [] && $kesalahan === []) {
            throw ValidationException::withMessages([
                'berkas' => 'Berkas tidak memiliki baris data untuk diimpor (FR-15).',
            ]);
        }

        $draf = Impor::create([
            'user_id' => $user->id,
            'nama_berkas' => $berkas->getClientOriginalName(),
            'status' => 'menunggu_konfirmasi',
            'token' => bin2hex(random_bytes(20)),
            'jumlah_baris' => count($semuaBaris),
            'berhasil' => 0,
            'gagal' => count($kesalahan),
            'baris_valid' => $barisValid,
            'kesalahan' => $kesalahan,
            'kedaluwarsa_token' => now()->addMinutes(30),
        ]);

        Aktivitas::catat(
            $user, 'tambah', 'impor', $draf->id,
            sprintf(
                'Pratinjau impor %s: %d baris valid, %d baris invalid (FR-15)',
                $draf->nama_berkas, count($barisValid), count($kesalahan)
            )
        );

        return [
            'id' => $draf->id,
            'token' => $draf->token,
            'kedaluwarsa_token' => $draf->kedaluwarsa_token?->toDateTimeString(),
            'nama_berkas' => $draf->nama_berkas,
            'jumlah_baris' => $draf->jumlah_baris,
            'valid' => count($barisValid),
            'gagal' => count($kesalahan),
            'baris_valid' => $barisValid,
            'kesalahan' => $kesalahan,
        ];
    }

    /**
     * Menuliskan baris valid draf impor setelah token konfirmasi cocok (FR-15).
     *
     * @return array{total: int, berhasil: int, gagal: int,
     *              detail_gagal: array<int, string>, barang: list<array<string, mixed>>}
     */
    public function simpan(User $user, int $idImpor, string $token): array
    {
        $impor = Impor::query()->find($idImpor)
            ?? throw ValidationException::withMessages([
                'id' => 'Data impor tidak ditemukan.',
            ]);

        if (! $impor->masihValid()) {
            $impor->forceFill(['status' => 'kedaluwarsa', 'waktu_selesai' => now()])->save();

            throw ValidationException::withMessages([
                'token' => 'Token konfirmasi sudah kedaluwarsa atau impor tidak menunggu konfirmasi; lakukan pratinjau ulang (FR-15).',
            ]);
        }

        if (! hash_equals($impor->token, (string) $token)) {
            throw ValidationException::withMessages([
                'token' => 'Token konfirmasi tidak valid (FR-15).',
            ]);
        }

        $tercatat = [];
        $detailGagal = [];

        foreach ($impor->baris_valid ?? [] as $item) {
            $ada = Barang::query()->whereNull('deleted_at')->where('kode_sku', $item['kode_sku'])->exists();

            if ($ada) {
                $detailGagal[$item['baris']] = "Kode SKU {$item['kode_sku']} sudah ada sebagai barang aktif saat konfirmasi.";
                continue;
            }

            $barang = Barang::query()->create([
                'kode_sku' => $item['kode_sku'],
                'nama' => $item['nama'],
                'kategori' => $item['kategori'],
                'satuan' => $item['satuan'],
                'stok_minimum' => (int) $item['stok_minimum'],
                'created_by' => $user->id,
            ]);

            $tercatat[] = [
                'id' => $barang->id,
                'baris' => $item['baris'],
                'kode_sku' => $barang->kode_sku,
                'nama' => $barang->nama,
            ];
        }

        $gagal = count($detailGagal);
        $berhasil = count($tercatat);

        $impor->forceFill([
            'status' => 'selesai',
            'berhasil' => $berhasil,
            'gagal' => $gagal,
            'kesalahan' => $detailGagal !== [] ? $detailGagal : ($impor->kesalahan ?? []),
            'waktu_selesai' => now(),
        ])->save();

        if ($berhasil > 0) {
            ExpiryMonitoringService::notifikasiStokMenipisSekarang();
        }

        Aktivitas::catat(
            $user, 'tambah', 'impor', $impor->id,
            "Konfirmasi impor {$impor->nama_berkas}: {$berhasil} barang dibuat, {$gagal} baris gagal (FR-15)"
        );

        return [
            'total' => $berhasil + $gagal,
            'berhasil' => $berhasil,
            'gagal' => $gagal,
            'detail_gagal' => $detailGagal,
            'barang' => $tercatat,
        ];
    }

    /**
     * Batas ukuran berkas dibaca dari pengaturan (BR-06).
     */
    private function pastikanUkuran(UploadedFile $berkas): void
    {
        $maksMb = max(1, (int) Pengaturan::nilai('maks_ukuran_impor_mb', '5'));
        $maksByte = $maksMb * 1024 * 1024;

        if ($berkas->getSize() > $maksByte) {
            throw ValidationException::withMessages([
                'berkas' => "Ukuran berkas melebihi batas {$maksMb} MB sesuai pengaturan (BR-06).",
            ]);
        }
    }

    /**
     * Baca seluruh baris data (mulai baris 2) via IOFactory::createReaderForFile.
     *
     * @return array{array<string, int>, list<array<string, string|int|float|null>>}
     */
    private function bacaBerkas(UploadedFile $berkas): array
    {
        $path = $berkas->getRealPath();

        try {
            $pembaca = IOFactory::createReaderForFile($path);
            $pembaca->setReadDataOnly(true);
            $bukuKerja = $pembaca->load($path);
            $sheet = $bukuKerja->getActiveSheet();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'berkas' => 'Berkas tidak dapat dibaca; gunakan format Excel (.xlsx/.xls) yang valid (FR-15).',
            ]);
        }

        // Peta header: label -> indeks kolom
        $headerMentah = $sheet->rangeToArray("A1:{$sheet->getHighestColumn()}1", null, true, true, true);
        $headerIndeks = [];

        foreach ($headerMentah[1] as $kolom => $label) {
            $kunci = mb_strtolower(trim((string) $label));

            if ($kunci !== '') {
                $headerIndeks[$kunci] = $kolom;
            }
        }

        foreach (self::HEADER_WAJIB as $kolomWajib) {
            if (! isset($headerIndeks[$kolomWajib])) {
                $bukuKerja->disconnectWorksheets();

                throw ValidationException::withMessages([
                    'berkas' => 'Kolom wajib tidak lengkap: '.implode(', ', self::HEADER_WAJIB).' (FR-15).',
                ]);
            }
        }

        $batasBaris = max(10, (int) Pengaturan::nilai('maks_baris_impor', '1000'));
        $koleksiBaris = [];
        $jumlahDisimpan = 0;

        foreach ($sheet->getRowIterator(2) as $nomorBaris => $kotakBaris) {
            if ($jumlahDisimpan + 1 > $batasBaris) {
                $bukuKerja->disconnectWorksheets();

                throw ValidationException::withMessages([
                    'berkas' => "Jumlah baris melebihi batas pengaturan ({$batasBaris}) (BR-06).",
                ]);
            }

            $sel = $sheet->rangeToArray("A{$nomorBaris}:{$sheet->getHighestColumn()}{$nomorBaris}", null, true, true, true);
            $satuBaris = $sel[$nomorBaris] ?? [];

            $kaburSeluruhnya = implode('', array_map(fn ($nilai) => trim(is_array($nilai) ? '' : (string) $nilai), $satuBaris));

            if ($kaburSeluruhnya === '') {
                continue; // baris kosong dilewati, bukan kesalahan
            }

            $koleksiBaris[$nomorBaris] = $satuBaris;
            $jumlahDisimpan++;
        }

        $bukuKerja->disconnectWorksheets();

        return [$headerIndeks, $koleksiBaris];
    }

    /**
     * Tarik data satu baris berdasarkan indeks kolom header.
     *
     * @param  array<string, string|int|float|null>  $satuBaris
     * @param  array<string, string>  $headerIndeks
     * @return array<string, string>
     */
    private function tarikData(array $satuBaris, array $headerIndeks): array
    {
        $data = [];

        foreach (self::HEADER_WAJIB as $kunci) {
            $nilai = $satuBaris[$headerIndeks[$kunci]] ?? '';

            $data[$kunci] = is_array($nilai) ? '' : trim((string) $nilai);
        }

        return $data;
    }

    /**
     * Validasi konten satu baris impor (FR-15).
     *
     * @param  array<string, string>  $data
     * @return list<string>
     */
    private function validasiBaris(array $data): array
    {
        $masalah = [];

        if ($data['kode_sku'] === '') {
            $masalah[] = 'kode_sku wajib diisi (FR-15).';
        } elseif (mb_strlen($data['kode_sku']) > 50) {
            $masalah[] = 'kode_sku maksimal 50 karakter.';
        }

        if ($data['nama'] === '') {
            $masalah[] = 'nama wajib diisi (FR-15).';
        } elseif (mb_strlen($data['nama']) > 150) {
            $masalah[] = 'nama maksimal 150 karakter.';
        }

        if ($data['kategori'] === '') {
            $masalah[] = 'kategori wajib diisi; gunakan: '.implode(', ', self::KATEGORI).'.';
        } elseif (! in_array($data['kategori'], self::KATEGORI, true)) {
            $masalah[] = "kategori '{$data['kategori']}' tidak dikenali; gunakan: ".implode(', ', self::KATEGORI).'.';
        }

        if ($data['satuan'] === '') {
            $masalah[] = 'satuan wajib diisi.';
        } elseif (mb_strlen($data['satuan']) > 30) {
            $masalah[] = 'satuan maksimal 30 karakter.';
        }

        if ($data['stok_minimum'] === '') {
            $masalah[] = 'stok_minimum wajib diisi (FR-09).';
        } elseif (filter_var($data['stok_minimum'], FILTER_VALIDATE_INT) === false || (int) $data['stok_minimum'] < 0) {
            $masalah[] = 'stok_minimum harus bilangan bulat >= 0 (FR-09).';
        }

        return $masalah;
    }
}
