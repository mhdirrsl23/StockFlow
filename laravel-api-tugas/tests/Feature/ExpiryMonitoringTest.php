<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\BatchStok;
use App\Models\Notifikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExpiryMonitoringTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tiga batch pada satu barang: mendekati ambang, kedaluwarsa, dan aman.
     *
     * @return array{0: User, 1: Barang, 2: BatchStok, 3: BatchStok, 4: BatchStok}
     */
    private function siapkanBatch(): array
    {
        $pemilik = User::factory()->pemilik()->create();
        $barang = Barang::factory()->create();

        $mendekati = BatchStok::factory()->create([
            'barang_id' => $barang->id,
            'nomor_batch' => 'MENDEKATI-1',
            'tanggal_kedaluwarsa' => Carbon::today()->addDays(3)->toDateString(),
            'status' => 'Aman',
        ]);

        $kedaluwarsa = BatchStok::factory()->create([
            'barang_id' => $barang->id,
            'nomor_batch' => 'KEDALUWARSA-1',
            'tanggal_kedaluwarsa' => Carbon::yesterday()->toDateString(),
            'status' => 'Aman',
        ]);

        $aman = BatchStok::factory()->create([
            'barang_id' => $barang->id,
            'nomor_batch' => 'AMAN-1',
            'tanggal_kedaluwarsa' => Carbon::today()->addDays(120)->toDateString(),
            'status' => 'Aman',
        ]);

        return [$pemilik, $barang, $mendekati, $kedaluwarsa, $aman];
    }

    public function test_pemantauan_harian_memperbarui_status_dan_membuat_notifikasi(): void
    {
        [$pemilik, $barang, $mendekati, $kedaluwarsa, $aman] = $this->siapkanBatch();

        $this->artisan('stockflow:monitor')->assertExitCode(0);

        $this->assertSame('Mendekati Kedaluwarsa', $mendekati->refresh()->status);
        $this->assertSame('Kedaluwarsa', $kedaluwarsa->refresh()->status);
        $this->assertSame('Aman', $aman->refresh()->status);

        $this->assertTrue(Notifikasi::where('jenis', 'Mendekati Kedaluwarsa')->where('batch_id', $mendekati->id)->exists());
        $this->assertTrue(Notifikasi::where('jenis', 'Kedaluwarsa')->where('batch_id', $kedaluwarsa->id)->exists());
        $this->assertFalse(Notifikasi::where('batch_id', $aman->id)->exists());
    }

    public function test_pemantauan_idempoten_tidak_menggandakan_notifikasi(): void
    {
        $this->siapkanBatch();

        $this->artisan('stockflow:monitor')->assertExitCode(0);
        $pertama = Notifikasi::count();
        $this->artisan('stockflow:monitor')->assertExitCode(0);

        $this->assertSame($pertama, Notifikasi::count());
    }

    public function test_endpoint_monitoring_menyajikan_ringkasan(): void
    {
        [$pemilik, $barang, $mendekati, $kedaluwarsa] = $this->siapkanBatch();
        $this->artisan('stockflow:monitor')->assertExitCode(0);

        $res = $this->actingAs($pemilik)->getJson('/api/expiry-monitoring');

        $res->assertOk()->assertJsonPath('success', true);

        $res2 = $this->actingAs($pemilik)->getJson('/api/expiry-monitoring/perlu-ditangani');
        $res2->assertOk();

        $ids = collect($res2->json('data'))->pluck('batch_id')->all();
        $this->assertContains($kedaluwarsa->id, $ids);
    }
}
