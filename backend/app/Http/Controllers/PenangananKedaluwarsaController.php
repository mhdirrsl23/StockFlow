<?php

namespace App\Http\Controllers;

use App\Http\Requests\PenangananRequest;
use App\Services\StockTransactionService;
use App\Support\ApiResponse;
use App\Exceptions\StokException;
use Illuminate\Http\JsonResponse;

final class PenangananKedaluwarsaController extends Controller
{
    public function __construct(private readonly StockTransactionService $stockService)
    {
    }

    /**
     * FR-12: catat tindakan penanganan batch kedaluwarsa melalui
     * mutasi 'penanganan' yang bisa diaudit; status batch Pemilik/Peran bebas.
     */
    public function store(PenangananRequest $request): JsonResponse
    {
        $params = $request->validated();

        try {
            $hasil = $this->stockService->penanganan($request->user(), $params);
        } catch (StokException $e) {
            return ApiResponse::fail($e->pesan, 422, $e->errors);
        }

        return ApiResponse::created($hasil, 'Penanganan kedaluwarsa berhasil dicatat (FR-12)');
    }
}
