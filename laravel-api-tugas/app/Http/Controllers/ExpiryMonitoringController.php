<?php

namespace App\Http\Controllers;

use App\Models\BatchStok;
use App\Models\Barang;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ExpiryMonitoringController extends Controller
{
    /**
     * FR-10 / FR-20: daftar batch sesuai tingkat urgensi; status disegarkan
     * saat dipanggil agar tetap sinkron dengan hari WIB (BR-03).
     */
    public function index(Request $request): JsonResponse
    {
        app(\App\Services\ExpiryMonitoringService::class)->jalankan();

        $query = BatchStok::query()
            ->with('barang:id,kode_sku,nama,satuan')
            ->fefo();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', ['Mendekati Kedaluwarsa', 'Kedaluwarsa']);
        }

        if ($barangId = $request->query('barang_id')) {
            $query->where('barang_id', (int) $barangId);
        }

        if ($dari = $request->query('dari')) {
            $query->whereDate('tanggal_kedaluwarsa', '>=', $dari);
        }

        if ($sampai = $request->query('sampai')) {
            $query->whereDate('tanggal_kedaluwarsa', '<=', $sampai);
        }

        $paginasi = $query->paginate(min(100, max(1, (int) ($request->query('per_page', 20)))))->appends($request->query());

        $items = collect($paginasi->items())->map(function (BatchStok $batch) {
            return [
                'id' => $batch->id,
                'barang_id' => $batch->barang_id,
                'kode_sku' => $batch->barang->kode_sku,
                'nama_barang' => $batch->barang->nama,
                'nomor_batch' => $batch->nomor_batch,
                'jumlah_sisa' => $batch->jumlah_sisa,
                'satuan' => $batch->barang->satuan,
                'tanggal_kedaluwarsa' => $batch->tanggal_kedaluwarsa->toDateString(),
                'sisa_hari' => $batch->sisaHari(),
                'status' => $batch->status,
                'status_penanganan' => $batch->status_penanganan,
            ];
        });

        $hitunganStatus = DB::table('batch_stok')->whereNull('deleted_at')
            ->select('status', DB::raw('COUNT(*) AS jumlah'))
            ->whereIn('status', ['Aman', 'Mendekati Kedaluwarsa', 'Kedaluwarsa', 'Ditangani'])
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        return ApiResponse::ok([
            'items' => $items,
            'hitungan_status' => $hitunganStatus,
            'pagination' => [
                'halaman' => $paginasi->currentPage(),
                'per_halaman' => $paginasi->perPage(),
                'total_baris' => $paginasi->total(),
                'halaman_terakhir' => $paginasi->lastPage(),
            ],
        ], 'Daftar monitoring kedaluwarsa ditemukan');
    }

    /**
     * Data pembantu daftar "Perlu Ditangani" langsung dari view (FR-26).
     */
    public function perluDitangani(): JsonResponse
    {
        $items = DB::table('v_perlu_ditangani')
            ->orderBy('tanggal_kedaluwarsa')
            ->get();

        return ApiResponse::ok($items, 'Daftar perlu ditangani ditemukan');
    }

    public function fefo(Barang $barang): JsonResponse
    {
        $items = $barang->batches()
            ->layakJual()
            ->fefo()
            ->get(['id', 'nomor_batch', 'jumlah_sisa', 'tanggal_kedaluwarsa']);

        return ApiResponse::ok($items, 'Rekomendasi FEFO ditemukan');
    }
}
