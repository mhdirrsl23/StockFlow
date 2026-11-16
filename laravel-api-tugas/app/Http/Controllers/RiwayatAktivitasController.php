<?php

namespace App\Http\Controllers;

use App\Models\RiwayatAktivitas;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class RiwayatAktivitasController extends Controller
{
    /**
     * FR-31: pemilik melihat seluruh riwayat; staf hanya miliknya sendiri,
     * dengan filter aksi dan rentang waktu (NFR-08: audit tak terubah).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = $user->isPemilik()
            ? RiwayatAktivitas::query()
            : RiwayatAktivitas::query()->where('user_id', $user->id);

        $query->with('pengguna:id,nama,username');

        if ($aksi = $request->query('aksi')) {
            $query->where('aksi', $aksi);
        }

        if ($dari = $request->query('dari')) {
            $query->where('waktu', '>=', Carbon::parse($dari)->startOfDay());
        }

        if ($sampai = $request->query('sampai')) {
            $query->where('waktu', '<=', Carbon::parse($sampai)->endOfDay());
        }

        if ($entitas = $request->query('entitas')) {
            $query->where('entitas', $entitas);
        }

        $paginasi = $query->orderBy('waktu', 'desc')->orderBy('id', 'desc')
            ->paginate(min(100, max(1, (int) ($request->query('per_page', 20)))))->appends($request->query());

        return ApiResponse::ok([
            'items' => $paginasi->items(),
            'pagination' => [
                'halaman' => $paginasi->currentPage(),
                'per_halaman' => $paginasi->perPage(),
                'total_baris' => $paginasi->total(),
                'halaman_terakhir' => $paginasi->lastPage(),
            ],
        ], 'Riwayat aktivitas ditemukan');
    }
}
