<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function ok(mixed $data = null, string $pesan = 'Berhasil', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $pesan,
            'data' => $data,
        ], $status);
    }

    public static function created(mixed $data = null, string $pesan = 'Data berhasil dibuat'): JsonResponse
    {
        return self::ok($data, $pesan, 201);
    }

    public static function fail(string $pesan = 'Permintaan gagal', int $status = 400, mixed $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $pesan,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
