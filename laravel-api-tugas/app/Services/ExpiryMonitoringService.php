<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\BatchStok;
use App\Models\Notifikasi;
use App\Models\Pengaturan;
use Illuminate\Support\Facades\DB;

final class ExpiryMonitoringService
{
    public function jalankan(): array
    {
        $mendekati = $this->refreshStatusDanNotifikasiMendekati();
        $kedaluwarsa = $this->notifikasiKedaluwarsa();
        $stokMenipis = self::notifikasiStokMenipisSekarang();

        return [
            'barang' => Barang::query()->count(),
            'mendekati' => $mendekati,
            'kedaluwarsa' => $kedaluwarsa,
            'stok_menipis' => $stokMenipis,
        ];
    }

    private function refreshStatusDanNotifikasiMendekati(): int
    {
        $this->segarkanStatus();

        $batches = BatchStok::query()
            ->where('status', 'Mendekati Kedaluwarsa')
            ->where('jumlah_sisa', '>', 0)
            ->with('barang')
            ->get();

        return $this->notifikasiBatches($batches, 'Mendekati Kedaluwarsa');
    }

    private function notifikasiKedaluwarsa(): int
    {
        $batches = BatchStok::query()
            ->where('status', 'Kedaluwarsa')
            ->where('jumlah_sisa', '>', 0)
            ->with('barang')
            ->get();

        return $this->notifikasiBatches($batches, 'Kedaluwarsa');
    }

    private function segarkanStatus(): void
    {
        $ambang = Pengaturan::ambangPeringatanHari();

        DB::update(<<<'SQL'
            UPDATE `batch_stok`
            SET `status` = CASE
                WHEN `tanggal_kedaluwarsa` < CURDATE() THEN 'Kedaluwarsa'
                WHEN `tanggal_kedaluwarsa` <= DATE_ADD(CURDATE(), INTERVAL :ambang DAY) THEN 'Mendekati Kedaluwarsa'
                ELSE 'Aman'
            END
            WHERE `deleted_at` IS NULL
              AND `status` <> 'Ditangani'
        SQL, ['ambang' => $ambang]);
    }

    private function notifikasiBatches(iterable $batches, string $jenis): int
    {
        $jumlahBaru = 0;

        foreach ($batches as $batch) {
            if ($batch->barang === null) {
                continue;
            }

            $pesan = $jenis === 'Kedaluwarsa'
                ? "Batch {$batch->nomor_batch} ({$batch->barang->nama}) sudah kedaluwarsa. Perlu ditangani."
                : sprintf(
                    'Batch %s (%s) mendekati kedaluwarsa: %d hari lagi.',
                    $batch->nomor_batch, $batch->barang->nama, max(0, $batch->sisaHari()),
                );

            $dibuat = Notifikasi::catat($jenis, $batch->barang_id, $batch->id, $pesan);

            if ($dibuat->wasRecentlyCreated) {
                $jumlahBaru++;
            }
        }

        return $jumlahBaru;
    }

    public static function notifikasiStokMenipisSekarang(): int
    {
        $rows = DB::table('v_stok_tersedia')->where('status_stok', 'Stok Menipis')->get();
        $jumlahBaru = 0;

        foreach ($rows as $row) {

            if ((int) $row->stok_minimum <= 0) {
                continue;
            }

            $dibuat = Notifikasi::catat(
                'Stok Menipis',
                (int) $row->barang_id,
                null,
                sprintf('Stok %s menipis: sisa %d %s, minimum %d %s.', $row->nama, $row->total_stok_tersedia, $row->satuan, $row->stok_minimum, $row->satuan),
            );

            if ($dibuat->wasRecentlyCreated) {
                $jumlahBaru++;
            }
        }

        return $jumlahBaru;
    }
}
