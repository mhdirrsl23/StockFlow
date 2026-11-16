<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_staf_ditolak_di_endpoint_khusus_pemilik_br08(): void
    {
        $staf = User::factory()->create();

        $this->actingAs($staf)->getJson('/api/pengaturan')
            ->assertForbidden()
            ->assertJsonPath('message', fn (string $pesan) => str_contains($pesan, 'BR-08'));

        $this->actingAs($staf)->postJson('/api/users', [
            'nama' => 'Akun Baru',
            'username' => 'akunbaru',
            'password' => 'rahasia123',
        ])->assertForbidden();

        $this->actingAs($staf)->getJson('/api/reports/stock')->assertForbidden();
        $this->actingAs($staf)->getJson('/api/reports/export?jenis=stok&format=xlsx')->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'akunbaru']);
    }

    public function test_staf_mengakses_endpoint_operasional(): void
    {
        $staf = User::factory()->create();

        $this->actingAs($staf)->getJson('/api/products')->assertOk()->assertJsonPath('success', true);
        $this->actingAs($staf)->getJson('/api/batches')->assertOk();
        $this->actingAs($staf)->getJson('/api/dashboard')->assertOk();
    }

    public function test_pemilik_mengakses_seluruh_endpoint_pengelolaan(): void
    {
        $pemilik = User::factory()->pemilik()->create();

        $this->actingAs($pemilik)->getJson('/api/pengaturan')->assertOk()->assertJsonPath('success', true);
        $this->actingAs($pemilik)->getJson('/api/users')->assertOk();
        $this->actingAs($pemilik)->getJson('/api/reports/stock')->assertOk();
    }

    public function test_tanpa_sesi_khusus_pemilik_juga_ditolak(): void
    {
        $this->getJson('/api/pengaturan')->assertUnauthorized();
        $this->getJson('/api/users')->assertUnauthorized();
    }
}
