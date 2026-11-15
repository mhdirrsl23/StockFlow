<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Support\Aktivitas;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    /**
     * FR-02: login (throttle 5/menit via middleware throttle:login).
     */
    public function login(LoginRequest $request): mixed
    {
        $user = User::query()->where('username', $request->input('username'))->first();

        if ($user === null || ! password_verify((string) $request->input('password'), $user->password)) {
            return ApiResponse::fail('Username atau password salah (FR-02).', 401);
        }

        if (! $user->aktif) {
            Aktivitas::catat($user, 'login', 'users', $user->id, 'Percobaan login akun nonaktif');

            return ApiResponse::fail('Akun dinonaktifkan oleh pemilik; hubungi pemilik toko (FR-05).', 403);
        }

        // FR-05: token lama dicabut saat login sukses berikutnya agar sesi tunggal.
        $user->tokens()->delete();

        $token = $user->createToken('stockflow-mobile')->plainTextToken;

        Aktivitas::catat($user, 'login', 'users', $user->id, "Login {$user->peran} ({$user->username})");

        return ApiResponse::ok([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'nama' => $user->nama,
                'username' => $user->username,
                'peran' => $user->peran,
                'aktif' => $user->aktif,
            ],
        ], 'Login berhasil');
    }

    /**
     * Info profil via token (FR-02) — dipakai Android saat membuka aplikasi.
     */
    public function me(Request $request): mixed
    {
        $user = $request->user();

        return ApiResponse::ok([
            'id' => $user->id,
            'nama' => $user->nama,
            'username' => $user->username,
            'peran' => $user->peran,
            'aktif' => $user->aktif,
        ], 'Profil ditemukan');
    }

    /**
     * FR-02: logout membatalkan token (BR: sesi tunggal).
     */
    public function logout(Request $request): mixed
    {
        $user = $request->user();
        $user->currentAccessToken()?->delete();

        Aktivitas::catat($user, 'logout', 'users', $user->id, "Logout {$user->username}");

        return ApiResponse::ok(null, 'Logout berhasil');
    }
}
