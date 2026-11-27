<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {

        RateLimiter::for('login', function (Request $request) {
            $kunci = mb_strtolower((string) $request->input('username')).'|'.$request->ip();

            return Limit::perMinute(5)->by($kunci)->response(function () {
                return response()->json([
                    'success' => false,
                    'message' => 'Terlalu banyak percobaan login. Tunggu setidaknya satu menit sebelum mencoba kembali (FR-02).',
                ], 429);
            });
        });
    }
}
