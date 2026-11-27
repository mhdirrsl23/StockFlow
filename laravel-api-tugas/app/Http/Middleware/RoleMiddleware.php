<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $peranIzin = 'pemilik,staf'): Response
    {
        $izin = preg_split('/[|,]/', $peranIzin) ?: [];
        $peranPengguna = (string) ($request->user()?->peran ?? '');

        if (! in_array($peranPengguna, $izin, true)) {
            $akses = $izin === ['pemilik']
                ? 'Akses ditolak: endpoint ini khusus pemilik (BR-08).'
                : 'Akses ditolak: peran Anda tidak memiliki izin untuk menu ini.';

            abort(403, $akses);
        }

        return $next($request);
    }
}
