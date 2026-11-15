<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * barang — FR-07 s/d FR-10, FR-15, FR-16; AB-01, AB-04, AB-07, AB-09.
     * Kategori daftar tetap (Q-09) sebagai atribut ENUM, tanpa tabel kategori.
     * Soft delete: deleted_at + deleted_by (AB-09, AB-08).
     */
    public function up(): void
    {
        Schema::create('barang', function (Blueprint $table) {
            $table->comment('Master barang; total stok = SUM(jumlah_sisa) batch aktif (AB-01)');
            $table->id();
            $table->string('kode_sku', 50)->comment('Unik untuk barang aktif (AB-07)');
            $table->string('nama', 150);
            $table->enum('kategori', ['Makanan', 'Minuman', 'Obat', 'Kebutuhan Rumah', 'Lainnya']);
            $table->string('satuan', 30)->comment('mis. pcs, box, pack');
            // Ambang Stok Menipis (AB-04)
            $table->unsignedInteger('stok_minimum')->default(0);
            // Pembuat data — basis otorisasi hapus staf (AB-08)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index('nama', 'idx_barang_nama');
            $table->index('kategori', 'idx_barang_kategori');
        });

        // Kolom bantu UNIQUE (PORTING SQL): MySQL memperlakukan NULL sebagai berbeda,
        // sehingga UNIQUE(kode_sku, deleted_at) saja tidak mengunci barang aktif.
        // Dengan menormalisasi NULL, SKU barang aktif (deleted_at NULL) benar-benar
        // unik, sementara barang terhapus memakai deleted_at sehingga SKU bisa dipakai ulang.
        DB::statement(
            "ALTER TABLE `barang` ADD COLUMN `deleted_key` TIMESTAMP(6) GENERATED ALWAYS AS (IFNULL(`deleted_at`, '1999-12-31 23:59:59.000000')) STORED"
        );
        DB::statement(
            'ALTER TABLE `barang` ADD UNIQUE KEY `uq_barang_sku_aktif` (`kode_sku`, `deleted_key`)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('barang');
    }
};
