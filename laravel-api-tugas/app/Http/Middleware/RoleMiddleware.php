<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memvalidasi peran parameter route-middleware: role:pemilik|staf (Bagian 2 PRD).
 */
final class RoleMiddleware
{
    /**
     * @param  string  $peranIzin  pemisah `|`, mis. "pemilik|staf"
     */
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
