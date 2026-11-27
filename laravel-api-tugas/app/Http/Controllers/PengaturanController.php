<?php

namespace App\Http\Controllers;

use App\Http\Requests\PengaturanRequest;
use App\Models\Pengaturan;
use App\Support\Aktivitas;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PengaturanController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::ok(
            Pengaturan::query()->orderBy('kunci')->get(['kunci', 'nilai', 'updated_at']),
            'Daftar pengaturan ditemukan'
        );
    }

    public function update(PengaturanRequest $request): JsonResponse
    {
        $valid = array_filter($request->validated(), fn ($nilai) => $nilai !== null);
        $lupa = [];

        foreach ($valid as $kunci => $nilai) {
            $lama = Pengaturan::nilai($kunci, '(tidak ada)');
            Pengaturan::atur($kunci, (string) $nilai);
            $lupa[] = "Atur {$kunci} menjadi {$nilai} (sebelumnya {$lama})";
        }

        Aktivitas::catat($request->user(), 'ubah_pengaturan', 'pengaturan', null, implode('; ', $lupa));

        return ApiResponse::ok(
            Pengaturan::query()->orderBy('kunci')->get(['kunci', 'nilai', 'updated_at']),
            'Pengaturan berhasil diperbarui'
        );
    }
}
