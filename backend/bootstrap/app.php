<?php

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias role untuk pembatasan peran di endpoint (Bagian 2 PRD).
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Seluruh respons kegagalan API membentuk {success, message, ...} (PRD 9.5)
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi tidak valid atau token tidak tersedia. Silakan login kembali (FR-02).',
                ], 401);
            }

            return null;
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() !== '' && $e->getMessage() !== 'This action is unauthorized.'
                        ? $e->getMessage()
                        : 'Akses ditolak: khusus pemilik (BR-08).',
                ], 403);
            }

            return null;
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $e->errors(),
                ], 422);
            }

            return null;
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $pesan = $e->getMessage();

                // Jangan bocorkan kelas model dari pesan binding (NFR-01);
                // hanya pesan kustom abort() yang boleh lewat.
                $bolehMuncul = ! str_contains($pesan, '\\') && ! str_starts_with($pesan, 'No query results');

                return response()->json([
                    'success' => false,
                    'message' => $bolehMuncul && $pesan !== '' ? $pesan : 'Data tidak ditemukan',
                ], 404);
            }

            return null;
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $pesan = $e->getMessage();

                $jusunFilter = str_contains($pesan, '\\') || str_starts_with($pesan, 'No query results');

                return response()->json([
                    'success' => false,
                    'message' => ! $jusunFilter && $pesan !== '' ? $pesan : 'Permintaan tidak dapat diproses',
                ], $e->getStatusCode(), $e->getHeaders());
            }

            return null;
        });
    })->create();
