<?php

namespace App\Http\Controllers;

use App\Models\BatchStok;
use App\Models\Barang;
use App\Models\Lampiran;
use App\Models\User;
use App\Support\Aktivitas;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class UploadLampiranController extends Controller
{
    /**
     * FR-14: unggah foto bukti batch/penanganan (JPG/PNG, batas via pengaturan).
     */
    public function store(Request $request, BatchStok $batch): JsonResponse
    {
        $data = $request->validate([
            'jenis' => ['required', 'in:bukti_batch,bukti_penanganan'],
            'lampiran' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:'.$this->batasKb()],
        ], [
            'lampiran.max' => 'Ukuran foto melebihi batas yang ditetapkan (NFR-01).',
        ]);

        $berkas = $data['lampiran'];
        $path = 'lampiran/'.now()->format('Y/m').'/'.mb_strtolower(\Illuminate\Support\Str::uuid()->toString()).'.'.$berkas->extension();

        Storage::disk('local')->put(
            $path,
            file_get_contents($berkas->getRealPath()),
        );

        // Nama asli tidak pernah dipercaya (NFR-01) — simpan nama file UUID.
        $lampiran = Lampiran::create([
            'batch_id' => $batch->id,
            'jenis' => $data['jenis'],
            'path' => $path,
        ]);

        return ApiResponse::created([
            'id' => $lampiran->id,
            'batch_id' => $lampiran->batch_id,
            'jenis' => $lampiran->jenis,
            'file' => route('lampiran.file', ['lampiran' => $lampiran->id]),
        ], 'Lampiran berhasil diunggah (FR-14)');
    }

    private function batasKb(): int
    {
        return (max(1, (int) \App\Models\Pengaturan::nilai('maks_ukuran_lampiran_mb', '2')) * 1024) - 1;
    }
}
