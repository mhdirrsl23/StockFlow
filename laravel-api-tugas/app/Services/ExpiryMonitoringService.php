<?php

namespace App\Services;

use App\Models\BatchStok;
use App\Models\Barang;
use App\Models\Notifikasi;
use App\Models\Pengaturan;
use Illuminate\Support\Facades\DB;

/**
 * Monitoring harian kedaluwarsa + stok minimum (FR-23, BR-03):
 * - status batch disegarkan dari tanggal kedaluwarsa terhadap hari WIB;
 * - notifikasi dibuat sekali per jenis per barang/batch per hari (uq index),
 *   sehingga proses idempoten saat dijalankan berulang;
 * - stok menipis diperiksa per barang dan juga dapat dipicu dari
 *   layanan transaksi stok setelah mutasi keluar.
 */
final class ExpiryMonitoringService
{
    /**
     * Refresh status batch, buat notifikasi kedaluwarsa & stok menipis.
     *
     * @return array{barang:int, mendekati:int, kedaluwarsa:int, stok_menipis:int}
     */
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

    /**
     * Set ulang status batch aktif (FR-20) lalu buat notifikasi
     * "Mendekati Kedaluwarsa" untuk batch yang berstatus tersebut.
     */
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

    /**
     * Notifikasi "Kedaluwarsa" untuk batch yang melewati tanggal kedaluwarsa.
     */
    private function notifikasiKedaluwarsa(): int
    {
        $batches = BatchStok::query()
            ->where('status', 'Kedaluwarsa')
            ->where('jumlah_sisa', '>', 0)
            ->with('barang')
            ->get();

        return $this->notifikasiBatches($batches, 'Kedaluwarsa');
    }

    /**
     * Pembaruan status semua batch aktif dari tanggal kedaluwarsa (FR-20, BR-03):
     * - tanggal < hari ini  -> Kedaluwarsa
     * - tanggal <= hari ini + ambang -> Mendekati Kedaluwarsa
     * - selain itu -> Aman
     * Batch berstatus Ditangani mengikuti aturan penanganan (FR-26).
     */
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

    /**
     * Anti-duplikat FR-23 dijelaskan oleh UNIQUE (jenis, barang, batch, tanggal WIB):
     * baris lama tidak dibuat kembali ketika monitor dijalankan berulang.
     *
     * @param  iterable<BatchStok>  $batches
     */
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

    /**
     * Buat notifikasi "Stok Menipis" untuk barang yang berstatus menipis hari ini
     * (dedupe per hari karena UNIQUE index tabel notifikasi).
     */
    public static function notifikasiStokMenipisSekarang(): int
    {
        $rows = DB::table('v_stok_tersedia')->where('status_stok', 'Stok Menipis')->get();
        $jumlahBaru = 0;

        foreach ($rows as $row) {
            // stok_minimum 0 berarti barang tidak berlangganan peringatan minimum
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
