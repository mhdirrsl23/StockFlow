<?php

namespace App\Http\Controllers;

use App\Exceptions\StokException;
use App\Http\Requests\MutasiStokRequest;
use App\Models\MutasiStok;
use App\Services\StockTransactionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class MutasiStokController extends Controller
{
    public function __construct(private readonly StockTransactionService $stockService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = $user->isPemilik()
            ? MutasiStok::query()
            : MutasiStok::query()->where('user_id', $user->id);

        $query->with(['batch:id,barang_id,nomor_batch', 'barang:id,kode_sku,nama,satuan', 'pengguna:id,nama,username']);

        if ($jenis = $request->query('jenis')) {
            $query->where('jenis', $jenis);
        }

        if ($barangId = $request->query('barang_id')) {
            $query->whereHas('batch', fn ($q) => $q->where('barang_id', (int) $barangId));
        }

        if ($batchId = $request->query('batch_id')) {
            $query->where('batch_id', (int) $batchId);
        }

        if ($dari = $request->query('dari')) {
            $query->where('waktu', '>=', Carbon::parse($dari)->startOfDay());
        }

        if ($sampai = $request->query('sampai')) {
            $query->where('waktu', '<=', Carbon::parse($sampai)->endOfDay());
        }

        $paginasi = $query->orderBy('waktu')->orderBy('id')
            ->paginate(min(100, max(1, (int) ($request->query('per_page', 20)))))->appends($request->query());

        return ApiResponse::ok([
            'items' => $paginasi->items(),
            'pagination' => [
                'halaman' => $paginasi->currentPage(),
                'per_halaman' => $paginasi->perPage(),
                'total_baris' => $paginasi->total(),
                'halaman_terakhir' => $paginasi->lastPage(),
            ],
        ], 'Daftar transaksi stok ditemukan');
    }

    public function store(MutasiStokRequest $request): JsonResponse
    {
        $params = $request->validated();
        $user = $request->user();
        $jenis = $params['jenis'];

        try {
            $hasil = match ($jenis) {
                'masuk' => $this->stockService->masuk($user, $params),
                'keluar' => $this->stockService->keluar($user, $params),
                'penyesuaian' => $this->stockService->penyesuaian($user, $params),
                default => throw ValidationException::withMessages(['jenis' => 'Jenis transaksi tidak dikenali.']),
            };
        } catch (StokException $e) {
            return ApiResponse::fail($e->pesan, 422, $e->errors);
        }

        $pesanKesalahan = match ($jenis) {
            'masuk' => 'Transaksi stok masuk berhasil dicatat (FR-06)',
            'keluar' => 'Transaksi stok keluar berhasil dicatat sesuai alokasi FEFO (FR-07)',
            default => 'Penyesuaian stok berhasil dicatat (FR-13)',
        };

        return ApiResponse::created($hasil, $pesanKesalahan);
    }
}
