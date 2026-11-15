<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\BatchStok;
use App\Models\MutasiStok;
use App\Models\Notifikasi;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;

final class DashboardController extends Controller
{
    /**
     * FR-03: ringkasan sesuai peran: jumlah barang/batch, stok menipis,
     * keperluan kedaluwarsa, transaksi terbaru, rekomendasi FEFO.
     */
    public function index(): mixed
    {
        $stokMenipis = DB::table('v_stok_tersedia')
            ->where('status_stok', 'Stok Menipis')
            ->orderBy('total_stok_tersedia')
            ->limit(5)
            ->get(['barang_id', 'kode_sku', 'nama', 'total_stok_tersedia', 'stok_minimum', 'satuan']);

        $perluDitangani = DB::table('v_perlu_ditangani')
            ->orderBy('tanggal_kedaluwarsa')
            ->limit(5)
            ->get();

        $mendekatiKedaluwarsa = BatchStok::query()
            ->where('status', 'Mendekati Kedaluwarsa')
            ->whereNull('deleted_at')
            ->where('jumlah_sisa', '>', 0)
            ->fefo()
            ->with('barang:id,kode_sku,nama,satuan')
            ->limit(5)
            ->get(['id', 'barang_id', 'nomor_batch', 'jumlah_sisa', 'tanggal_kedaluwarsa', 'status']);

        $rekomendasiFefo = BatchStok::query()
            ->layakJual()
            ->fefo()
            ->with('barang:id,kode_sku,nama,satuan')
            ->limit(5)
            ->get(['id', 'barang_id', 'nomor_batch', 'jumlah_sisa', 'tanggal_kedaluwarsa', 'status']);

        $transaksiTerbaru = MutasiStok::query()
            ->with(['batch:id,barang_id,nomor_batch', 'pengguna:id,nama'])
            ->latest('waktu')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'batch_id', 'jenis', 'jumlah', 'waktu', 'user_id']);

        $jumlahNotifikasiBelumDibaca = Notifikasi::query()->whereNull('ditampilkan_pada')->count();

        return ApiResponse::ok([
            'ringkasan' => [
                'total_barang_aktif' => Barang::query()->whereNull('deleted_at')->count(),
                'total_batch_aktif' => BatchStok::query()->whereNull('deleted_at')->count(),
                'stok_menipis' => DB::table('v_stok_tersedia')->where('status_stok', 'Stok Menipis')->count(),
                'batch_mendekati_kedaluwarsa' => DB::table('batch_stok')->where('status', 'Mendekati Kedaluwarsa')->whereNull('deleted_at')->where('jumlah_sisa', '>', 0)->count(),
                'batch_kedaluwarsa' => DB::table('batch_stok')->where('status', 'Kedaluwarsa')->whereNull('deleted_at')->where('jumlah_sisa', '>', 0)->count(),
                'notifikasi_belum_dibaca' => $jumlahNotifikasiBelumDibaca,
            ],
            'stok_menipis' => $stokMenipis,
            'perlu_ditangani' => $perluDitangani,
            'mendekati_kedaluwarsa' => $mendekatiKedaluwarsa,
            'rekomendasi_fefo' => $rekomendasiFefo,
            'transaksi_terbaru' => $transaksiTerbaru,
        ], 'Ringkasan dashboard ditemukan');
    }
}
