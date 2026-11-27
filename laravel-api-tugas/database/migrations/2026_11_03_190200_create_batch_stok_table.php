<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_stok', function (Blueprint $table) {
            $table->comment('Batch stok per barang dengan tanggal kedaluwarsa masing-masing');
            $table->id();
            $table->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
            $table->string('nomor_batch', 100);

            $table->unsignedInteger('jumlah_awal');

            $table->unsignedInteger('jumlah_sisa');
            $table->date('tanggal_kedaluwarsa');
            $table->enum('status', ['Aman', 'Mendekati Kedaluwarsa', 'Kedaluwarsa', 'Ditangani'])->default('Aman')
                ->comment('FR-20 / diagram status Bagian 4.3');

            $table->enum('status_penanganan', ['Dimusnahkan', 'Dikembalikan ke Pemasok', 'Lainnya'])->nullable();
            $table->text('catatan_penanganan')->nullable()->comment('Wajib bila status_penanganan = Lainnya');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['barang_id', 'tanggal_kedaluwarsa'], 'idx_batch_barang_exp');

            $table->index('tanggal_kedaluwarsa', 'idx_batch_kedaluwarsa');
            $table->index('status', 'idx_batch_status');
        });

        DB::statement(
            "ALTER TABLE `batch_stok` ADD COLUMN `deleted_key` TIMESTAMP(6) GENERATED ALWAYS AS (IFNULL(`deleted_at`, '1999-12-31 23:59:59.000000')) STORED"
        );
        DB::statement(
            'ALTER TABLE `batch_stok` ADD UNIQUE KEY `uq_batch_nomor_aktif` (`barang_id`, `nomor_batch`, `deleted_key`)'
        );

        DB::statement(
            'ALTER TABLE `batch_stok`
                ADD CONSTRAINT `chk_batch_sisa_nonnegatif` CHECK (`jumlah_sisa` >= 0),
                ADD CONSTRAINT `chk_batch_sisa_maks_awal` CHECK (`jumlah_sisa` <= `jumlah_awal`)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_stok');
    }
};
