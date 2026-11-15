<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RiwayatAktivitas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status'  => false,
                'message' => 'Username atau password salah'
            ], 401);
        }

        if (!$user->aktif) {
            return response()->json([
                'status'  => false,
                'message' => 'Akun Anda dinonaktifkan. Silakan hubungi pemilik.'
            ], 403);
        }

        $token = $user->createToken('android_token')->plainTextToken;

        RiwayatAktivitas::create([
            'user_id'    => $user->id,
            'aksi'       => 'login',
            'entitas'    => 'users',
            'entitas_id' => $user->id,
            'keterangan' => "User {$user->nama} ({$user->peran}) berhasil login",
            'waktu'      => now(),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Login berhasil',
            'token'   => $token,
            'user'    => [
                'id'       => $user->id,
                'nama'     => $user->nama,
                'username' => $user->username,
                'peran'    => $user->peran,
            ]
        ], 200);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        RiwayatAktivitas::create([
            'user_id'    => $user->id,
            'aksi'       => 'logout',
            'entitas'    => 'users',
            'entitas_id' => $user->id,
            'keterangan' => "User {$user->nama} logout",
            'waktu'      => now(),
        ]);

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Logout berhasil'
        ], 200);
    }

    public function me(Request $request)
    {
        return response()->json([
            'status' => true,
            'user'   => $request->user()
        ], 200);
    }
}