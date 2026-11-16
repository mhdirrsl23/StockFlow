<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManajemenAkunTest extends TestCase
{
    use RefreshDatabase;

    private User $pemilik;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pemilik = User::factory()->pemilik()->create(['username' => 'budi_pemilik']);
    }

    public function test_pemilik_melihat_daftar_akun_tanpa_kolom_sensitif(): void
    {
        $staf = User::factory()->create(['username' => 'rina_staf']);

        $res = $this->actingAs($this->pemilik)->getJson('/api/users');

        $res->assertOk()->assertJsonPath('success', true);

        $body = $res->json('data');
        $this->assertNotEmpty($body['items']);

        $rina = collect($body['items'])->firstWhere('username', 'rina_staf');
        $this->assertNotNull($rina);
        $this->assertArrayNotHasKey('password', $rina);
        $this->assertArrayNotHasKey('remember_token', $rina);
    }

    public function test_pemilik_membuat_akun_staf_baru(): void
    {
        $res = $this->actingAs($this->pemilik)->postJson('/api/users', [
            'nama' => 'Dewi Lestari',
            'username' => 'dewi',
            'password' => 'rahasia123',
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.peran', 'staf')
            ->assertJsonPath('data.username', 'dewi')
            ->assertJsonPath('data.aktif', true);

        $this->assertDatabaseHas('users', ['username' => 'dewi', 'peran' => 'staf']);
    }

    public function test_password_terlalu_pendek_ditolak(): void
    {
        $res = $this->actingAs($this->pemilik)->postJson('/api/users', [
            'nama' => 'Dewi Lestari',
            'username' => 'dewi',
            'password' => '123',
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['password']]);
    }

    public function test_username_duplikat_ditolak(): void
    {
        User::factory()->create(['username' => 'rina_staf']);

        $res = $this->actingAs($this->pemilik)->postJson('/api/users', [
            'nama' => 'Rina Kedua',
            'username' => 'rina_staf',
            'password' => 'rahasia123',
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.username.0', fn (string $pesan) => str_contains($pesan, 'FR-05'));
    }

    public function test_pemilik_menonaktifkan_akun_staf(): void
    {
        $staf = User::factory()->create();

        $res = $this->actingAs($this->pemilik)->patchJson("/api/users/{$staf->id}/aktif", ['aktif' => false]);

        $res->assertOk()
            ->assertJsonPath('data.aktif', false);

        $this->assertFalse($staf->refresh()->aktif);
    }

    public function test_pemilik_tidak_bisa_menonaktifkan_diri_sendiri(): void
    {
        $res = $this->actingAs($this->pemilik)->patchJson("/api/users/{$this->pemilik->id}/aktif", ['aktif' => false]);

        $res->assertStatus(422)
            ->assertJsonPath('message', fn (string $pesan) => str_contains($pesan, 'FR-05'));

        $this->assertTrue($this->pemilik->refresh()->aktif);
    }

    public function test_username_akun_pemilik_tidak_dapat_diubah(): void
    {
        $res = $this->actingAs($this->pemilik)->putJson("/api/users/{$this->pemilik->id}", [
            'username' => 'budi_baru',
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('message', fn (string $pesan) => str_contains($pesan, 'FR-05'));

        $this->assertSame('budi_pemilik', $this->pemilik->refresh()->username);
    }

    public function test_pemilik_mengubah_nama_akun_staf(): void
    {
        $staf = User::factory()->create(['nama' => 'Nama Lama']);

        $res = $this->actingAs($this->pemilik)->putJson("/api/users/{$staf->id}", ['nama' => 'Nama Baru']);

        $res->assertOk()
            ->assertJsonPath('data.nama', 'Nama Baru');

        $this->assertSame('Nama Baru', $staf->refresh()->nama);
    }

    public function test_pemilik_menghapus_akun_staf(): void
    {
        $staf = User::factory()->create();
        $staf->createToken('uji');

        $res = $this->actingAs($this->pemilik)->deleteJson("/api/users/{$staf->id}");

        $res->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('users', ['id' => $staf->id]);
    }
}
