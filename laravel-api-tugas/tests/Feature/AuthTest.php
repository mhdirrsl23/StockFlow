<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_berhasil_mengembalikan_token_dan_profil(): void
    {
        $pemilik = User::factory()->pemilik()->create(['username' => 'budi']);

        $res = $this->postJson('/api/login', ['username' => 'budi', 'password' => 'password']);

        $res->assertOk();

        $body = $res->json();
        $this->assertTrue($body['success']);
        $this->assertIsString($body['data']['token']);
        $this->assertNotSame('', $body['data']['token']);
        $this->assertSame('budi', $body['data']['user']['username']);
        $this->assertSame('pemilik', $body['data']['user']['peran']);
        $this->assertTrue($body['data']['user']['aktif']);
    }

    public function test_login_password_salah_ditolak_dengan_pesan_fr02(): void
    {
        User::factory()->create(['username' => 'rina']);

        $res = $this->postJson('/api/login', ['username' => 'rina', 'password' => 'password-salah']);

        $res->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', fn (string $pesan) => str_contains($pesan, 'FR-02'));
    }

    public function test_login_tanpa_kolom_wajib_menghasilkan_validasi(): void
    {
        $res = $this->postJson('/api/login', []);

        $res->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors']);
    }

    public function test_login_akun_nonaktif_ditolak_dengan_pesan_fr05(): void
    {
        User::factory()->create(['username' => 'staf_off', 'aktif' => false]);

        $res = $this->postJson('/api/login', ['username' => 'staf_off', 'password' => 'password']);

        $res->assertForbidden()
            ->assertJsonPath('message', fn (string $pesan) => str_contains($pesan, 'FR-05'));
    }

    public function test_endpoint_profil_menampilkan_pengguna_sesi(): void
    {
        $staf = User::factory()->create(['nama' => 'Rina Wulandari']);

        $res = $this->actingAs($staf)->getJson('/api/me');

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.username', $staf->username)
            ->assertJsonPath('data.nama', 'Rina Wulandari');
    }

    public function test_logout_mencabut_token_sesi(): void
    {
        $staf = User::factory()->create();
        $token = $staf->createToken('uji')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        Auth::forgetGuards();

        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_permintaan_tanpa_sesi_ditolak(): void
    {
        $this->getJson('/api/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }
}
