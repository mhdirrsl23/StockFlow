<?php

namespace App\Services;

use App\Models\MutasiStok;
use Illuminate\Support\Carbon;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\DB;

final class LaporanExportService
{
    public function dataStok(?string $kategori = null): array
    {
        $kueri = DB::table('v_stok_tersedia')->orderBy('nama');

        if ($kategori !== null && $kategori !== '') {
            $kueri->where('kategori', $kategori);
        }

        $baris = $kueri->get()->map(fn ($r) => [
            $r->kode_sku,
            $r->nama,
            $r->kategori,
            $r->satuan,
            (int) $r->stok_minimum,
            (int) $r->total_stok_tersedia,
            $r->status_stok,
        ]);

        $judul = 'Laporan Stok Saat Ini';
        if ($kategori !== null && $kategori !== '') {
            $judul .= " — Kategori {$kategori}";
        }

        return $this->bentuk($judul, [
            'Kode SKU', 'Nama Barang', 'Kategori', 'Satuan', 'Stok Minimum', 'Stok Tersedia', 'Status Stok',
        ], $baris, 'laporan-stok');
    }

    public function dataTransaksi(?Carbon $dari = null, ?Carbon $sampai = null, ?string $kategori = null): array
    {
        $dari ??= Carbon::today(config('app.timezone'))->subDays(30)->startOfDay();
        $sampai ??= Carbon::today(config('app.timezone'))->endOfDay();

        $kueri = MutasiStok::query()
            ->with(['batch.barang', 'pengguna:id,nama,username'])
            ->whereHas('batch.barang', function ($kondisi) use ($kategori) {
                if ($kategori !== null && $kategori !== '') {
                    $kondisi->where('kategori', $kategori);
                }
            })
            ->whereBetween('waktu', [$dari, $sampai])
            ->orderBy('waktu')
            ->orderBy('id');

        $baris = $kueri->get()->map(fn (MutasiStok $mutasi) => [
            $mutasi->waktu?->toDateTimeString(),
            ucfirst($mutasi->jenis),
            $mutasi->batch->barang->kode_sku ?? '-',
            $mutasi->batch->barang->nama ?? '-',
            $mutasi->batch->nomor_batch ?? '-',
            $mutasi->jumlah,
            $mutasi->batch->barang->satuan ?? '-',
            (string) ($mutasi->catatan ?? ''),
            $mutasi->pengguna?->nama ?? '-',
        ]);

        $judul = sprintf('Laporan Transaksi Stok %s s/d %s', $dari->toDateString(), $sampai->toDateString());
        if ($kategori !== null && $kategori !== '') {
            $judul .= " — Kategori {$kategori}";
        }

        return $this->bentuk($judul, [
            'Waktu', 'Jenis', 'Kode SKU', 'Nama Barang', 'Nomor Batch', 'Jumlah', 'Satuan', 'Catatan', 'Oleh',
        ], $baris, 'laporan-transaksi');
    }

    public function dataKedaluwarsa(?Carbon $dari = null, ?Carbon $sampai = null, ?string $status = null): array
    {
        $kueri = DB::table('batch_stok')
            ->join('barang', 'barang.id', '=', 'batch_stok.barang_id')
            ->whereNull('batch_stok.deleted_at')
            ->whereNull('barang.deleted_at')
            ->orderBy('batch_stok.tanggal_kedaluwarsa')
            ->select([
                'barang.kode_sku', 'barang.nama', 'barang.satuan',
                'batch_stok.nomor_batch', 'batch_stok.jumlah_sisa',
                'batch_stok.tanggal_kedaluwarsa', 'batch_stok.status',
                'batch_stok.status_penanganan',
            ]);

        if ($status !== null && $status !== '') {
            $kueri->where('batch_stok.status', $status);
        }

        if ($dari !== null) {
            $kueri->whereDate('batch_stok.tanggal_kedaluwarsa', '>=', $dari->toDateString());
        }

        if ($sampai !== null) {
            $kueri->whereDate('batch_stok.tanggal_kedaluwarsa', '<=', $sampai->toDateString());
        }

        $hariIni = Carbon::today(config('app.timezone'));

        $baris = $kueri->get()->map(fn ($r) => [
            $r->kode_sku,
            $r->nama,
            $r->nomor_batch,
            (int) $r->jumlah_sisa,
            $r->satuan,
            (string) $r->tanggal_kedaluwarsa,
            (int) $hariIni->diffInDays(Carbon::parse($r->tanggal_kedaluwarsa), false),
            $r->status,
            (string) ($r->status_penanganan ?? ''),
        ]);

        $judul = 'Laporan Kedaluwarsa Batch';
        if ($status !== null && $status !== '') {
            $judul .= " — Status {$status}";
        }

        return $this->bentuk($judul, [
            'Kode SKU', 'Nama Barang', 'Nomor Batch', 'Jumlah Sisa', 'Satuan', 'Kedaluwarsa', 'Sisa Hari', 'Status', 'Status Penanganan',
        ], $baris, 'laporan-kedaluwarsa');
    }

    public function dataLaris(?Carbon $dari = null, ?Carbon $sampai = null): array
    {
        $dari ??= Carbon::today(config('app.timezone'))->subDays(30)->startOfDay();
        $sampai ??= Carbon::today(config('app.timezone'))->endOfDay();

        $baris = DB::table('mutasi_stok')
            ->join('batch_stok', 'batch_stok.id', '=', 'mutasi_stok.batch_id')
            ->join('barang', 'barang.id', '=', 'batch_stok.barang_id')
            ->where('mutasi_stok.jenis', 'keluar')
            ->whereNull('barang.deleted_at')
            ->whereBetween('mutasi_stok.waktu', [$dari, $sampai])
            ->groupBy('barang.id', 'barang.kode_sku', 'barang.nama', 'barang.kategori', 'barang.satuan')
            ->orderByDesc(DB::raw('SUM(mutasi_stok.jumlah)'))
            ->limit(50)
            ->get([
                'barang.kode_sku', 'barang.nama', 'barang.kategori', 'barang.satuan',
                DB::raw('SUM(mutasi_stok.jumlah) AS total_keluar'),
            ])
            ->values()
            ->map(fn ($r, $i) => [
                $i + 1,
                $r->kode_sku,
                $r->nama,
                $r->kategori,
                (int) $r->total_keluar,
                $r->satuan,
            ]);

        $judul = sprintf('Laporan Produk Terlaris %s s/d %s', $dari->toDateString(), $sampai->toDateString());

        return $this->bentuk($judul, [
            'Peringkat', 'Kode SKU', 'Nama Barang', 'Kategori', 'Total Keluar', 'Satuan',
        ], $baris, 'laporan-terlaris');
    }

    private function bentuk(string $judul, array $header, iterable $baris, string $dasarNama): array
    {
        return [
            'judul' => 'StockFlow — '.$judul,
            'judul_tabel' => $judul,
            'dibuat_pada' => now()->toDateTimeString(),
            'header' => $header,
            'rows' => $baris instanceof Enumerable ? $baris->all() : iterator_to_array($baris),
            'nama_berkas' => $dasarNama.'-'.now()->format('Ymd-His'),
        ];
    }
}
