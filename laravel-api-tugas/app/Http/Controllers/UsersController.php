<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\MutasiStok;
use App\Models\User;
use App\Support\Aktivitas;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

final class UsersController extends Controller
{
    /**
     * FR-02: login (rate limit 5 percobaan/menit via middleware throttle:login).
     */
    public function login(LoginRequest $request): mixed
    {
        $user = User::query()->where('username', $request->input('username'))->first();

        if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
            return ApiResponse::fail('Username atau password salah (FR-02).', 401);
        }

        if (! $user->aktif) {
            Aktivitas::catat($user, 'login', 'users', $user->id, 'Percobaan login akun nonaktif');

            return ApiResponse::fail('Akun dinonaktifkan oleh pemilik; hubungi pemilik toko (FR-05).', 403);
        }

        // FR-05: sesi tunggal — token lama dicabut ketika login sukses berikutnya.
        $user->tokens()->delete();

        $token = $user->createToken('stockflow-mobile')->plainTextToken;

        Aktivitas::catat($user, 'login', 'users', $user->id, "Login {$user->peran} ({$user->username})");

        return ApiResponse::ok($this->dataSesi($user, $token), 'Login berhasil');
    }

    /**
     * Info profil via token (FR-02) — dipakai Android saat aplikasi dibuka.
     */
    public function me(Request $request): mixed
    {
        return ApiResponse::ok($this->profil($request->user()), 'Profil ditemukan');
    }

    /**
     * FR-02: logout membatalkan token saat ini (BR: sesi tunggal).
     */
    public function logout(Request $request): mixed
    {
        $user = $request->user();
        $user->currentAccessToken()?->delete();

        Aktivitas::catat($user, 'logout', 'users', $user->id, "Logout {$user->username}");

        return ApiResponse::ok(null, 'Logout berhasil');
    }

    /**
     * FR-05 / BR-08 (rute): daftar seluruh akun untuk pemilik.
     */
    public function index(Request $request): JsonResponse
    {
        $items = User::query()
            ->orderBy('peran')
            ->orderBy('nama')
            ->get(['id', 'nama', 'username', 'peran', 'aktif', 'created_at', 'updated_at']);

        return ApiResponse::ok(['items' => $items], 'Daftar akun ditemukan');
    }

    /**
     * FR-05: pemilik membuat akun staf (tanpa registrasi mandiri; NFR-01).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'username' => [
                'required', 'string', 'max:50', Rule::unique('users', 'username'),
            ],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ], [
            'username.unique' => 'Username sudah dipakai akun lain (FR-05).',
            'password.min' => 'Password minimal 8 karakter (FR-05).',
        ]);

        $user = User::create([
            'nama' => $data['nama'],
            'username' => $data['username'],
            'password' => $data['password'],
            'peran' => 'staf',
            'aktif' => true,
        ]);

        Aktivitas::catat($request->user(), 'tambah', 'users', $user->id, "Tambah akun staf {$user->username} (FR-05)");

        return ApiResponse::created($this->profil($user), 'Akun staf berhasil dibuat');
    }

    /**
     * FR-05: pemilik memperbarui profil/kredensial akun bawahan.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['sometimes', 'string', 'max:100'],
            'username' => [
                'sometimes', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:255'],
        ], [
            'username.unique' => 'Username sudah dipakai akun lain (FR-05).',
            'password.min' => 'Password minimal 8 karakter (FR-05).',
        ]);

        if ($user->isPemilik() && isset($data['username']) && $data['username'] !== $user->username) {
            return ApiResponse::fail('Username akun pemilik tidak boleh diubah (FR-05).', 422, [
                'username' => ['Username akun pemilik tidak boleh diubah.'],
            ]);
        }

        $user->fill(array_filter([
            'nama' => $data['nama'] ?? null,
            'username' => $data['username'] ?? null,
        ], fn ($nilai) => $nilai !== null));

        if (array_key_exists('password', $data) && $data['password'] !== null) {
            $user->password = $data['password'];

            // Kredensial baru → sesi lama dicabut (FR-05)
            $user->tokens()->delete();
        }

        $user->save();

        Aktivitas::catat($request->user(), 'ubah', 'users', $user->id, "Ubah akun {$user->username}");

        return ApiResponse::ok($this->profil($user->refresh()), 'Akun berhasil diperbarui');
    }

    /**
     * FR-05: aktif/nonaktif akun dengan pencabutan seluruh token saat nonaktif.
     */
    public function ubahAktif(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'aktif' => ['required', 'boolean'],
        ], [], [
            'aktif' => 'status akun',
        ]);

        $aktif = (bool) $request->boolean('aktif');

        if ($user->id === $request->user()->id && ! $aktif) {
            return ApiResponse::fail('Anda tidak dapat menonaktifkan akun sendiri (FR-05).', 422, [
                'aktif' => ['Anda tidak dapat menonaktifkan akun sendiri.'],
            ]);
        }

        if (! $aktif && $user->isPemilik() && User::query()->where('peran', 'pemilik')->where('aktif', true)->count() <= 1) {
            return ApiResponse::fail('Minimal satu akun pemilik harus tetap aktif (FR-05).', 422, [
                'aktif' => ['Minimal satu akun pemilik harus tetap aktif.'],
            ]);
        }

        $user->update(['aktif' => $aktif]);

        if (! $aktif) {
            $user->tokens()->delete();
        }

        Aktivitas::catat(
            $request->user(), 'ubah', 'users', $user->id,
            $aktif ? "Aktifkan akun {$user->username}" : "Nonaktifkan akun {$user->username} dengan pencabutan token (FR-05)"
        );

        return ApiResponse::ok($this->profil($user->refresh()), $aktif ? 'Akun diaktifkan' : 'Akun dinonaktifkan dan seluruh sesi dicabut');
    }

    /**
     * FR-05 / BR-07: menghapus akun staf; akun berriwayat transaksi hanya dapat
     * dinonaktifkan agar audit tidak rusak (NFR-08).
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return ApiResponse::fail('Anda tidak dapat menghapus akun sendiri (FR-05).', 422, [
                'id' => ['Anda tidak dapat menghapus akun sendiri.'],
            ]);
        }

        if ($user->isPemilik() && User::query()->where('peran', 'pemilik')->count() <= 1) {
            return ApiResponse::fail('Minimal satu akun pemilik harus tetap ada (FR-05).', 422, [
                'id' => ['Minimal satu akun pemilik harus tetap ada.'],
            ]);
        }

        $punyaTransaksi = MutasiStok::query()->where('user_id', $user->id)->exists();

        if ($punyaTransaksi) {
            return ApiResponse::fail('Akun memiliki riwayat transaksi; gunakan nonaktifkan agar audit tetap terlacak (NFR-08).', 422, [
                'id' => ['Akun memiliki riwayat transaksi; akun hanya dapat dinonaktifkan.'],
            ]);
        }

        $username = $user->username;
        $user->tokens()->delete();
        $user->delete();

        Aktivitas::catat($request->user(), 'hapus', 'users', null, "Hapus akun {$username}");

        return ApiResponse::ok(null, 'Akun dihapus');
    }

    /**
     * Bentuk payload sesi login (FR-02).
     *
     * @return array<string, mixed>
     */
    private function dataSesi(User $user, string $token): array
    {
        return ['token' => $token, 'user' => $this->profil($user)];
    }

    /**
     * Bentuk profil ringkas tanpa pembocoran kolom sensitif (NFR-01).
     *
     * @return array<string, mixed>
     */
    private function profil(User $user): array
    {
        return [
            'id' => $user->id,
            'nama' => $user->nama,
            'username' => $user->username,
            'peran' => $user->peran,
            'aktif' => $user->aktif,
        ];
    }
}
