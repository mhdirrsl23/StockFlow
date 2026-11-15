<?php

namespace App\Http\Controllers;

use App\Models\BatchStok;
use App\Models\Lampiran;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final class LampiranController extends Controller
{
    /**
     * FR-14: file lampiran hanya dilihat pengguna login (bukan URL publik).
     */
    public function file(Lampiran $lampiran): Response
    {
        $path = Storage::disk('local')->path($lampiran->path);

        return response()->file($path, [
            'Content-Disposition' => "inline; filename=\"{$lampiran->id}.jpg\"",
        ]);
    }

    /**
     * FR-14: menghapus foto bukti — staf hanya lampiran batch miliknya sendiri.
     */
    public function destroy(Request $request, Lampiran $lampiran): JsonResponse
    {
        $user = $request->user();

        $bukti = BatchStok::query()->find($lampiran->batch_id);
        $pemilik = $bukti->created_by ?? null;
        $punyaAkses = $user->isPemilik() || $pemilik === $user->id;

        if (! $punyaAkses) {
            abort(403, 'Akses ditolak: hanya pembuat batch atau pemilik yang boleh menghapus lampiran (FR-14).');
        }

        Storage::disk('local')->delete($lampiran->path);
        $lampiran->delete();

        return ApiResponse::ok(null, 'Lampiran dihapus');
    }
}
