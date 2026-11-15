<?php

namespace App\Console\Commands;

use App\Services\ExpiryMonitoringService;
use Illuminate\Console\Command;

class StockflowMonitor extends Command
{
    /**
     * Signature command monitoring harian (FR-23): dijadwalkan 06.00 & 18.00 WIB.
     */
    protected $signature = 'stockflow:monitor';

    protected $description = 'Perbarui status kedaluwarsa batch dan buat notifikasi pemeriksaan harian (FR-23)';

    public function handle(ExpiryMonitoringService $monitor): int
    {
        $hasil = $monitor->jalankan();

        $this->info(sprintf(
            'Monitoring selesai — barang: %d, baru mendekati kedaluwarsa: %d, baru kedaluwarsa: %d, baru stok menipis: %d',
            $hasil['barang'],
            $hasil['mendekati'],
            $hasil['kedaluwarsa'],
            $hasil['stok_menipis'],
        ));

        return self::SUCCESS;
    }
}
