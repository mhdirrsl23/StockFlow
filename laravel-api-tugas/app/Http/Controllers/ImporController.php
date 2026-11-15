<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImporPreviewRequest;
use App\Models\Impor;
use App\Services\ImportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ImporController extends Controller
{
    public function __construct(private readonly ImportService $importService)
    {
    }

    /**
     * FR-15 langkah 1: validasi + pratinjau berkas; tidak ada penulisan barang.
     */
    public function preview(ImporPreviewRequest $request): JsonResponse
    {
        $hasil = $this->importService->menganalisis($request->user(), $request->file('berkas'), );

        return ApiResponse::ok($hasil, 'Pratinjau impor tersimpan; konfirmasi dengan token dalam 30 menit (FR-15)');
    }

    /**
     * FR-15 langkah 2: konfirmasi dengan token → barang valid dibuat.
     */
    public function commit(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'integer'],
            'token' => ['required', 'string', 'max:80'],
        ]);

        $hasil = $this->importService->simpan(
            $request->user(),
            (int) $request->input('id'),
            (string) $request->input('token'),
        );

        return ApiResponse::created($hasil, 'Impor selesai — ringkasan hasil menyertai (FR-15)');
    }

    /**
     * Riwayat impor milik pengguna (kewirausahaan audit FR-31).
     */
    public function index(Request $request): JsonResponse
    {
        $kueri = Impor::query()->with('pengguna:id,nama,username');

        if (! $request->user()->isPemilik()) {
            $kueri->where('user_id', $request->user()->id);
        }

        $paginasi = $kueri->latest('id')
            ->paginate(min(100, max(1, (int) ($request->query('per_page', 15)))))
            ->appends($request->query());

        return ApiResponse::ok([
            'items' => $paginasi->items(),
            'pagination' => [
                'halaman' => $paginasi->currentPage(),
                'per_halaman' => $paginasi->perPage(),
                'total_baris' => $paginasi->total(),
                'halaman_terakhir' => $paginasi->lastPage(),
            ],
        ], 'Riwayat impor ditemukan');
    }

    /**
     * Detail satu draf/key imporan (token tidak pernah dibocorkan ulang).
     */
    public function show(Request $request, Impor $impor): JsonResponse
    {
        if (! $request->user()->isPemilik() && $impor->user_id !== $request->user()->id) {
            return ApiResponse::fail('Akses ditolak: impor ini bukan milik Anda.', 403);
        }

        return ApiResponse::ok($this->bentuk($impor), 'Data impor ditemukan');
    }

    /**
     * Bentuk ringkas tanpa mengekspos token demi keamanan (NFR-01).
     */
    private function bentuk(Impor $impor): array
    {
        return [
            'id' => $impor->id,
            'nama_berkas' => $impor->nama_berkas,
            'status' => $impor->status,
            'jumlah_baris' => $impor->jumlah_baris,
            'berhasil' => $impor->berhasil,
            'gagal' => $impor->gagal,
            'kesalahan' => $impor->kesalahan,
            'kedaluwarsa_token' => $impor->kedaluwarsa_token?->toDateTimeString(),
            'waktu_selesai' => $impor->waktu_selesai?->toDateTimeString(),
            'pengguna' => $impor->pengguna?->only(['id', 'nama', 'username']),
        ];
    }
}
