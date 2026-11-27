<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotifikasiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Notifikasi::query()
            ->with('barang:id,kode_sku,nama,satuan', 'batch:id,nomor_batch')
            ->jenis((string) $request->query('jenis'));

        if ($request->boolean('belum_ditampilkan')) {
            $query->whereNull('ditampilkan_pada');
        }

        $paginasi = $query->orderBy('created_at')
            ->orderBy('id')
            ->paginate(min(100, max(1, (int) ($request->query('per_page', 20)))))->appends($request->query());

        return ApiResponse::ok([
            'items' => $paginasi->items(),
            'pagination' => [
                'halaman' => $paginasi->currentPage(),
                'per_halaman' => $paginasi->perPage(),
                'total_baris' => $paginasi->total(),
                'halaman_terakhir' => $paginasi->lastPage(),
            ],
        ], 'Daftar notifikasi ditemukan');
    }

    public function tandaiDibaca(Request $request, Notifikasi $notifikasi): JsonResponse
    {
        if ($notifikasi->ditampilkan_pada === null) {
            $notifikasi->update(['ditampilkan_pada' => now()]);
        }

        return ApiResponse::ok([
            'id' => $notifikasi->id,
            'ditampilkan_pada' => $notifikasi->ditampilkan_pada,
        ], 'Notifikasi ditandai sebagai telah dibaca');
    }

    public function tandaiSemuaDibaca(Request $request): JsonResponse
    {
        $jumlah = Notifikasi::query()->whereNull('ditampilkan_pada')->update(['ditampilkan_pada' => now()]);

        return ApiResponse::ok(['jumlah' => $jumlah], 'Semua notifikasi ditandai sebagai telah dibaca');
    }
}
