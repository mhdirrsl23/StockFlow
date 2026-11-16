<?php

namespace Tests\Feature;

use App\Models\Barang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImporBarangTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Buat berkas xlsx riil berisi baris barang.
     *
     * @param  array<int, array<int, string|int>>  $baris
     */
    private function berkasXlsx(array $baris): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($baris as $i => $kolom) {
            foreach ($kolom as $j => $nilai) {
                $sheet->setCellValue([$j + 1, $i + 1], $nilai);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'impor_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            'impor.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    private function barisImpor(): array
    {
        return [
            ['kode_sku', 'nama', 'kategori', 'satuan', 'stok_minimum'],
            ['SKU-IMP-001', 'Keripik Singkong Balado', 'Makanan', 'pcs', 25],
            ['SKU-IMP-002', 'Susu Kotak Cokelat 1L', 'Minuman', 'ltr', 15],
        ];
    }

    public function test_preview_menyimpan_draf_dengan_token_konfirmasi(): void
    {
        $pemilik = User::factory()->pemilik()->create();

        $res = $this->actingAs($pemilik)->post('/api/imports/products/preview', [
            'berkas' => $this->berkasXlsx($this->barisImpor()),
        ]);

        $res->assertOk()->assertJsonPath('success', true);

        $data = $res->json('data');
        $this->assertSame(2, $data['valid']);
        $this->assertSame(0, $data['gagal']);
        $this->assertIsInt($data['id']);
        $this->assertIsString($data['token']);
        $this->assertNotSame('', $data['token']);
    }

    public function test_commit_membuat_barang_dari_baris_valid(): void
    {
        $pemilik = User::factory()->pemilik()->create();

        $preview = $this->actingAs($pemilik)->post('/api/imports/products/preview', [
            'berkas' => $this->berkasXlsx($this->barisImpor()),
        ])->json('data');

        $res = $this->actingAs($pemilik)->postJson('/api/imports/products/commit', [
            'id' => $preview['id'],
            'token' => $preview['token'],
        ]);

        $res->assertCreated()->assertJsonPath('success', true);
        $this->assertSame(2, $res->json('data.berhasil'));
        $this->assertSame(0, $res->json('data.gagal'));

        $this->assertSame(2, Barang::whereIn('kode_sku', ['SKU-IMP-001', 'SKU-IMP-002'])->count());
    }

    public function test_berkas_dengan_format_selain_excel_ditolak(): void
    {
        $pemilik = User::factory()->pemilik()->create();

        $res = $this->actingAs($pemilik)->post('/api/imports/products/preview', [
            'berkas' => UploadedFile::fake()->createWithContent('daftar.csv', "kode_sku,nama\nSKU-X,Y\n"),
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['berkas']]);
    }

    public function test_baris_kode_sku_duplikat_terdeteksi_pada_pratinjau(): void
    {
        $pemilik = User::factory()->pemilik()->create();

        $baris = $this->barisImpor();
        $baris[2][0] = $baris[1][0]; // baris 3 memakai SKU baris 2

        $res = $this->actingAs($pemilik)->post('/api/imports/products/preview', [
            'berkas' => $this->berkasXlsx($baris),
        ]);

        $res->assertOk();

        $data = $res->json('data');
        $this->assertGreaterThan(0, $data['gagal']);
        $this->assertLessThan(2, $data['valid']);
    }
}
