<?php

namespace App\Http\Controllers;

use App\Exceptions\StokException;
use App\Http\Requests\PenangananRequest;
use App\Services\StockTransactionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PenangananKedaluwarsaController extends Controller
{
    public function __construct(private readonly StockTransactionService $stockService) {}

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
