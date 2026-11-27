<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barang', function (Blueprint $table) {
            $table->comment('Master barang; total stok = SUM(jumlah_sisa) batch aktif (AB-01)');
            $table->id();
            $table->string('kode_sku', 50)->comment('Unik untuk barang aktif (AB-07)');
            $table->string('nama', 150);
            $table->enum('kategori', ['Makanan', 'Minuman', 'Obat', 'Kebutuhan Rumah', 'Lainnya']);
            $table->string('satuan', 30)->comment('mis. pcs, box, pack');

            $table->unsignedInteger('stok_minimum')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index('nama', 'idx_barang_nama');
            $table->index('kategori', 'idx_barang_kategori');
        });

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
