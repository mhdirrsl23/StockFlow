<?php

namespace App\Http\Controllers;

use App\Models\BatchStok;
use App\Models\Lampiran;
use App\Models\Pengaturan;
use App\Support\Aktivitas;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class LampiranController extends Controller
{
    public function store(Request $request, BatchStok $batch): JsonResponse
    {
        $data = $request->validate([
            'jenis' => ['required', 'in:bukti_batch,bukti_penanganan'],
            'lampiran' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:'.$this->batasKb()],
        ], [
            'lampiran.required' => 'Foto wajib dipilih (FR-14).',
            'lampiran.mimes' => 'Foto harus berformat JPG, JPEG, atau PNG (Q-05).',
            'lampiran.max' => 'Ukuran foto melebihi batas pengaturan (NFR-01).',
        ]);

        $berkas = $data['lampiran'];
        $path = 'lampiran/'.now()->format('Y/m').'/'.mb_strtolower(Str::uuid()->toString()).'.'.$berkas->extension();

        Storage::disk('local')->put($path, file_get_contents($berkas->getRealPath()));

        $lampiran = Lampiran::create([
            'batch_id' => $batch->id,
            'jenis' => $data['jenis'],
            'path' => $path,
        ]);

        Aktivitas::catat($request->user(), 'tambah', 'lampiran', $lampiran->id, "Unggah {$lampiran->jenis} untuk batch {$batch->nomor_batch}");

        return ApiResponse::created($lampiran->toArray(), 'Lampiran berhasil diunggah (FR-14)');
    }

    public function file(Lampiran $lampiran): Response
    {
        $penuh = Storage::disk('local')->path($lampiran->path);

        if (! is_file($penuh)) {
            abort(404, 'Berkas lampiran tidak ditemukan');
        }

        return response()->file($penuh, [
            'Content-Disposition' => "inline; filename=\"{$lampiran->id}.{$lampiran->ekstensi()}\"",
        ]);
    }

    public function destroy(Request $request, Lampiran $lampiran): JsonResponse
    {
        $user = $request->user();

        $batch = BatchStok::query()->withTrashed()->find($lampiran->batch_id);
        $punyaAkses = $user->isPemilik() || ($batch !== null && $batch->created_by === $user->id);

        if (! $punyaAkses) {
            abort(403, 'Akses ditolak: hanya pembuat batch atau pemilik yang boleh menghapus lampiran (FR-14).');
        }

        Storage::disk('local')->delete($lampiran->path);
        $lampiran->delete();

        Aktivitas::catat($user, 'hapus', 'lampiran', $lampiran->id, "Hapus {$lampiran->jenis}");

        return ApiResponse::ok(null, 'Lampiran dihapus');
    }

    private function batasKb(): int
    {
        return (max(1, (int) Pengaturan::nilai('maks_ukuran_lampiran_mb', '2')) * 1024) - 1;
    }
}
