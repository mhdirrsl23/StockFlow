<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * VIEW pendukung — AB-01, AB-04, FR-26 (PORTING SQL).
     * Server berjalan dengan zona waktu WIB (APP_TIMEZONE=Asia/Jakarta, MySQL system tz WIB)
     * sehingga CURDATE() sama dengan hari Jakarta.
     */
    public function up(): void
    {
        // Stok tersedia barang = jumlah_sisa seluruh batch yang belum kedaluwarsa
        // (tanggal >= hari ini) dan belum Ditangani, dari barang aktif (AB-01, AB-05).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW `v_stok_tersedia` AS
            SELECT
              b.id                                        AS barang_id,
              b.kode_sku,
              b.nama,
              b.kategori,
              b.satuan,
              b.stok_minimum,
              COALESCE(SUM(bt.jumlah_sisa), 0)            AS total_stok_tersedia,
              CASE
                WHEN COALESCE(SUM(bt.jumlah_sisa), 0) <= b.stok_minimum THEN 'Stok Menipis'
                ELSE 'Aman'
              END                                          AS status_stok
            FROM barang b
            LEFT JOIN batch_stok bt
                   ON bt.barang_id = b.id
                  AND bt.deleted_at IS NULL
                  AND bt.status <> 'Ditangani'
                  AND bt.tanggal_kedaluwarsa >= CURDATE()
            WHERE b.deleted_at IS NULL
            GROUP BY b.id, b.kode_sku, b.nama, b.kategori, b.satuan, b.stok_minimum
        SQL);

        // Daftar Perlu Ditangani: batch kedaluwarsa aktif yang belum Ditangani (FR-26).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW `v_perlu_ditangani` AS
            SELECT
              bt.id                                        AS batch_id,
              bt.barang_id,
              b.kode_sku,
              b.nama                                       AS nama_barang,
              bt.nomor_batch,
              bt.jumlah_sisa,
              b.satuan,
              bt.tanggal_kedaluwarsa,
              DATEDIFF(bt.tanggal_kedaluwarsa, CURDATE())  AS sisa_hari
            FROM batch_stok bt
            JOIN barang b ON b.id = bt.barang_id AND b.deleted_at IS NULL
            WHERE bt.deleted_at IS NULL
              AND bt.status = 'Kedaluwarsa'
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS `v_perlu_ditangani`');
        DB::statement('DROP VIEW IF EXISTS `v_stok_tersedia`');
    }
};
