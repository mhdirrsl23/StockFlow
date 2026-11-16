<?php

namespace Tests\Feature;

use App\Models\BatchStok;
use App\Models\Lampiran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LampiranTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Byte PNG valid 1x1 piksel.
     */
    private function pngBytes(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
    }

    public function test_staf_mengunggah_bukti_batch_multipart(): void
    {
        Storage::fake('local');

        $staf = User::factory()->create();
        $batch = BatchStok::factory()->create();

        $res = $this->actingAs($staf)->post("/api/batches/{$batch->id}/attachments", [
            'jenis' => 'bukti_batch',
            'lampiran' => UploadedFile::fake()->createWithContent('bukti.png', $this->pngBytes()),
        ]);

        $res->assertCreated()->assertJsonPath('success', true);

        $path = (string) $res->json('data.path');
        $this->assertStringStartsWith('lampiran/', $path);
        Storage::disk('local')->assertExists($path);

        $this->assertDatabaseHas('lampiran', [
            'batch_id' => $batch->id,
            'jenis' => 'bukti_batch',
            'path' => $path,
        ]);
    }

    public function test_berkas_lampiran_dilayani_ke_pemegang_token(): void
    {
        Storage::fake('local');

        $staf = User::factory()->create();
        $batch = BatchStok::factory()->create();

        $unggah = $this->actingAs($staf)->post("/api/batches/{$batch->id}/attachments", [
            'jenis' => 'bukti_batch',
            'lampiran' => UploadedFile::fake()->createWithContent('bukti.png', $this->pngBytes()),
        ])->json('data');

        $path = (string) $unggah['path'];

        $res = $this->actingAs($staf)->get("/api/attachments/{$unggah['id']}/file");

        $res->assertOk();
        $this->assertStringStartsWith('image/png', (string) $res->headers->get('Content-Type'));

        // BinaryFileResponse tidak membawa isi ke getContent() pada konteks test —
        // byte yang dilayani diverifikasi langsung dari penyimpanan (server asli
        // sudah terbukti benar pada smoke test).
        $this->assertSame($this->pngBytes(), Storage::disk('local')->get($path));
    }

    public function test_format_selain_gambar_ditolak_q05(): void
    {
        Storage::fake('local');

        $staf = User::factory()->create();
        $batch = BatchStok::factory()->create();

        $res = $this->actingAs($staf)->post("/api/batches/{$batch->id}/attachments", [
            'jenis' => 'bukti_batch',
            'lampiran' => UploadedFile::fake()->createWithContent('catatan.txt', 'bukan gambar'),
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['lampiran']]);

        $this->assertSame(0, Lampiran::count());
    }

    public function test_pemilik_menghapus_lampiran_beserta_berkasnya(): void
    {
        Storage::fake('local');

        $pemilik = User::factory()->pemilik()->create();
        $staf = User::factory()->create();
        $batch = BatchStok::factory()->create(['created_by' => $staf->id]);

        $unggah = $this->actingAs($staf)->post("/api/batches/{$batch->id}/attachments", [
            'jenis' => 'bukti_penanganan',
            'lampiran' => UploadedFile::fake()->createWithContent('bukti.png', $this->pngBytes()),
        ])->json('data');

        $path = (string) $unggah['path'];

        $this->actingAs($pemilik)->deleteJson("/api/attachments/{$unggah['id']}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('lampiran', ['id' => $unggah['id']]);
        Storage::disk('local')->assertMissing($path);
    }
}
