<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\BatchStok;
use App\Models\MutasiStok;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FefoTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function siapkanStok(): array
    {
        $pemilik = User::factory()->pemilik()->create();
        $barang = Barang::factory()->create(['satuan' => 'pcs']);

        $terdekat = BatchStok::factory()->create([
            'barang_id' => $barang->id,
            'nomor_batch' => 'BATCH-A',
            'jumlah_awal' => 20,
            'jumlah_sisa' => 20,
            'tanggal_kedaluwarsa' => Carbon::today()->addDays(7)->toDateString(),
            'status' => 'Aman',
        ]);

        $jauh = BatchStok::factory()->create([
            'barang_id' => $barang->id,
            'nomor_batch' => 'BATCH-B',
            'jumlah_awal' => 40,
            'jumlah_sisa' => 40,
            'tanggal_kedaluwarsa' => Carbon::today()->addDays(40)->toDateString(),
            'status' => 'Aman',
        ]);

        return [$pemilik, $barang, $terdekat, $jauh];
    }

    public function test_keluar_mengalokasikan_fefo_batch_terdekat_dahulu(): void
    {
        [$pemilik, $barang, $terdekat, $jauh] = $this->siapkanStok();

        $res = $this->actingAs($pemilik)->postJson('/api/stock-transactions', [
            'jenis' => 'keluar',
            'barang_id' => $barang->id,
            'jumlah' => 30,
            'catatan' => 'penjualan',
        ]);

        $res->assertCreated()->assertJsonPath('success', true);

        $body = $res->json('data');
        $this->assertCount(2, $body['transaksi']);
        $this->assertSame($terdekat->id, $body['transaksi'][0]['batch_id']);
        $this->assertSame(20, $body['transaksi'][0]['jumlah']);
        $this->assertSame($jauh->id, $body['transaksi'][1]['batch_id']);
        $this->assertSame(10, $body['transaksi'][1]['jumlah']);
        $this->assertSame(30, $body['sisa_layak_jual']);

        $this->assertSame(0, $terdekat->refresh()->jumlah_sisa);
        $this->assertSame(30, $jauh->refresh()->jumlah_sisa);
        $this->assertSame(2, MutasiStok::where('jenis', 'keluar')->count());
    }

    public function test_keluar_melebihi_stok_ditolak_tanpa_menulis_mutasi(): void
    {
        [, $barang, $terdekat, $jauh] = $this->siapkanStok();
        $pemilik = User::factory()->pemilik()->create();

        $res = $this->actingAs($pemilik)->postJson('/api/stock-transactions', [
            'jenis' => 'keluar',
            'barang_id' => $barang->id,
            'jumlah' => 61,
        ]);

        $res->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame(20, $terdekat->refresh()->jumlah_sisa);
        $this->assertSame(40, $jauh->refresh()->jumlah_sisa);
        $this->assertSame(0, MutasiStok::count());
    }

    public function test_batch_kedaluwarsa_tidak_dapat_dikeluarkan_langsung(): void
    {
        [$pemilik, $barang, $terdekat] = $this->siapkanStok();

        $terdekat->update([
            'status' => 'Kedaluwarsa',
            'tanggal_kedaluwarsa' => Carbon::yesterday()->toDateString(),
        ]);

        $res = $this->actingAs($pemilik)->postJson('/api/stock-transactions', [
            'jenis' => 'keluar',
            'barang_id' => $barang->id,
            'batch_id' => $terdekat->id,
            'jumlah' => 5,
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', fn (string $pesan) => str_contains($pesan, 'tidak layak dijual'));

        $this->assertSame(20, $terdekat->refresh()->jumlah_sisa);
    }

    public function test_penyesuaian_wajib_membawa_catatan_alasan(): void
    {
        [, $barang, $terdekat] = $this->siapkanStok();
        $pemilik = User::factory()->pemilik()->create();

        $res = $this->actingAs($pemilik)->postJson('/api/stock-transactions', [
            'jenis' => 'penyesuaian',
            'barang_id' => $barang->id,
            'batch_id' => $terdekat->id,
            'jumlah' => -5,
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['catatan']]);
    }

    public function test_penyesuaian_mengubah_saldo_batch_dan_tercatat(): void
    {
        [, $barang, $terdekat] = $this->siapkanStok();
        $pemilik = User::factory()->pemilik()->create();

        $res = $this->actingAs($pemilik)->postJson('/api/stock-transactions', [
            'jenis' => 'penyesuaian',
            'barang_id' => $barang->id,
            'batch_id' => $terdekat->id,
            'jumlah' => -5,
            'catatan' => 'rusak 5 pcs',
        ]);

        $res->assertCreated()->assertJsonPath('success', true);

        $this->assertSame(15, $terdekat->refresh()->jumlah_sisa);

        $mutasi = MutasiStok::where('jenis', 'penyesuaian')->where('batch_id', $terdekat->id)->first();
        $this->assertNotNull($mutasi);
        $this->assertSame(-5, $mutasi->jumlah);
    }

    public function test_stok_masuk_membuat_batch_baru_untuk_barang(): void
    {
        [, $barang] = $this->siapkanStok();
        $pemilik = User::factory()->pemilik()->create();

        $res = $this->actingAs($pemilik)->postJson('/api/stock-transactions', [
            'jenis' => 'masuk',
            'barang_id' => $barang->id,
            'nomor_batch' => 'BATCH-C',
            'tanggal_kedaluwarsa' => Carbon::today()->addDays(90)->toDateString(),
            'jumlah' => 25,
            'catatan' => 'penerimaan PO',
        ]);

        $res->assertCreated()->assertJsonPath('success', true);

        $batch = BatchStok::where('nomor_batch', 'BATCH-C')->first();
        $this->assertNotNull($batch);
        $this->assertSame(25, $batch->jumlah_sisa);

        $this->assertSame(1, MutasiStok::where('jenis', 'masuk')->where('batch_id', $batch->id)->count());
    }
}
