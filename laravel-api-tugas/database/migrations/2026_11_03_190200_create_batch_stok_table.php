<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * batch_stok — FR-11 s/d FR-14, FR-20, FR-26; AB-01, AB-02, AB-03, AB-05.
     * Satu barang punya banyak batch; inti fitur Smart Expiry Monitoring.
     * Status dihitung sistem tiap hari (FR-23): sisa_hari = tanggal_kedaluwarsa - hari ini (WIB).
     * Ambang bawaan 30 hari, dapat diubah pemilik (FR-25, tabel pengaturan).
     */
    public function up(): void
    {
        Schema::create('batch_stok', function (Blueprint $table) {
            $table->comment('Batch stok per barang dengan tanggal kedaluwarsa masing-masing');
            $table->id();
            $table->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
            $table->string('nomor_batch', 100);
            // Jumlah saat batch dibuat
            $table->unsignedInteger('jumlah_awal');
            // Stok tersedia batch; tidak boleh negatif (AB-06)
            $table->unsignedInteger('jumlah_sisa');
            $table->date('tanggal_kedaluwarsa');
            $table->enum('status', ['Aman', 'Mendekati Kedaluwarsa', 'Kedaluwarsa', 'Ditangani'])->default('Aman')
                ->comment('FR-20 / diagram status Bagian 4.3');
            // FR-26; wajib terisi saat status = Ditangani
            $table->enum('status_penanganan', ['Dimusnahkan', 'Dikembalikan ke Pemasok', 'Lainnya'])->nullable();
            $table->text('catatan_penanganan')->nullable()->comment('Wajib bila status_penanganan = Lainnya');
            // Pembuat data — basis otorisasi hapus staf (AB-08)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            // Urutan bawaan FR-12 dan alokasi FEFO (AB-02)
            $table->index(['barang_id', 'tanggal_kedaluwarsa'], 'idx_batch_barang_exp');
            // Filter rentang tanggal FR-15, FR-34
            $table->index('tanggal_kedaluwarsa', 'idx_batch_kedaluwarsa');
            $table->index('status', 'idx_batch_status');
        });

        // Kolom bantu UNIQUE (PORTING SQL), pola sama dengan barang.deleted_key:
        // nomor_batch unik per barang di antara batch aktif (kunci NULL dinormalisasi).
        DB::statement(
            "ALTER TABLE `batch_stok` ADD COLUMN `deleted_key` TIMESTAMP(6) GENERATED ALWAYS AS (IFNULL(`deleted_at`, '1999-12-31 23:59:59.000000')) STORED"
        );
        DB::statement(
            'ALTER TABLE `batch_stok` ADD UNIQUE KEY `uq_batch_nomor_aktif` (`barang_id`, `nomor_batch`, `deleted_key`)'
        );

        // Constraint porting dari SQL (MySQL 8 menegakkan CHECK).
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
