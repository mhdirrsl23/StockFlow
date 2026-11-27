<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Aktivitas;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PersonalAccessTokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tokens = DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $request->user()->id)
            ->orderByDesc('last_used_at')
            ->get(['id', 'name', 'last_used_at', 'created_at']);

        $sibak = $request->user()->currentAccessToken()?->id;

        $items = $tokens->map(fn ($token) => [
            'id' => $token->id,
            'nama' => $token->name,
            'sesi_saat_ini' => $token->id === $sibak,
            'dipakai_terakhir_pada' => $token->last_used_at,
            'dibuat_pada' => $token->created_at,
        ]);

        return ApiResponse::ok(['items' => $items], 'Daftar sesi ditemukan');
    }

    public function destroy(Request $request, int $token): JsonResponse
    {
        $dihapus = DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $request->user()->id)
            ->where('id', $token)
            ->delete();

        if ($dihapus === 0) {
            return ApiResponse::fail('Sesi tidak ditemukan atau bukan milik Anda (FR-05).', 404);
        }

        Aktivitas::catat($request->user(), 'logout', 'users', $request->user()->id, "Cabut sesi #{$token} (FR-05)");

        return ApiResponse::ok(null, 'Sesi berhasil dicabut');
    }

    public function hapusLainnya(Request $request): JsonResponse
    {
        $idSaatIni = (int) ($request->user()->currentAccessToken()?->id ?? 0);

        $jumlah = DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $request->user()->id)
            ->where('id', '!=', $idSaatIni)
            ->delete();

        Aktivitas::catat($request->user(), 'logout', 'users', $request->user()->id, "Cabut {$jumlah} sesi lain (FR-05)");

        return ApiResponse::ok(['jumlah' => $jumlah], 'Seluruh sesi lain berhasil dicabut');
    }
}
