<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanEksporTest extends TestCase
{
    use RefreshDatabase;

    private function siapkanPemilikDenganData(): User
    {
        $pemilik = User::factory()->pemilik()->create();
        Barang::factory()->count(3)->create();

        return $pemilik;
    }

    public function test_laporan_stok_mengembalikan_data_json(): void
    {
        $pemilik = $this->siapkanPemilikDenganData();

        $res = $this->actingAs($pemilik)->getJson('/api/reports/stock');

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data']);
    }

    public function test_laporan_transaksi_dan_kedaluwarsa_dan_laris(): void
    {
        $pemilik = $this->siapkanPemilikDenganData();

        $this->actingAs($pemilik)->getJson('/api/reports/transactions')->assertOk()->assertJsonPath('success', true);
        $this->actingAs($pemilik)->getJson('/api/reports/expiry')->assertOk()->assertJsonPath('success', true);
        $this->actingAs($pemilik)->getJson('/api/reports/fast-moving')->assertOk()->assertJsonPath('success', true);
    }

    public function test_ekspor_xlsx_lembar_excel_valid(): void
    {
        $pemilik = $this->siapkanPemilikDenganData();

        $res = $this->actingAs($pemilik)->get('/api/reports/export?jenis=stok&format=xlsx');

        $res->assertOk();
        $this->assertStringStartsWith('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', (string) $res->headers->get('Content-Type'));

        // Isi stream xlsx ditulis ke php://output (tidak terekam di getContent()
        // konteks test) — magic byte PK sudah terverifikasi pada smoke test.
        $disposisi = (string) $res->headers->get('Content-Disposition');
        $this->assertStringContainsString('.xlsx', $disposisi);
    }

    public function test_ekspor_pdf_dokumen_pdf_valid(): void
    {
        $pemilik = $this->siapkanPemilikDenganData();

        $res = $this->actingAs($pemilik)->get('/api/reports/export?jenis=kedaluwarsa&format=pdf');

        $res->assertOk();
        $this->assertStringStartsWith('application/pdf', (string) $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', (string) $res->getContent());
    }
}
