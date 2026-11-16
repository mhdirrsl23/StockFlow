<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * notifikasi — FR-23, FR-24; 5.9 B.
     * Dibuat penjadwal Laravel 06.00 & 18.00 WIB, dibaca WorkManager tiap 12 jam.
     * Ditujukan ke seluruh pengguna (Q-04), maka tidak ada kolom user.
     * Aturan anti-duplikat FR-23: satu baris per jenis per batch/barang per hari.
     */
    public function up(): void
    {
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->comment('Catatan notifikasi yang diambil Android via API (FR-24)');
            $table->id();
            $table->enum('jenis', ['Mendekati Kedaluwarsa', 'Kedaluwarsa', 'Stok Menipis']);
            // Selalu terisi; WAJIB untuk jenis Stok Menipis
            $table->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();
            // Terisi untuk kedua jenis kedaluwarsa
            $table->foreignId('batch_id')->nullable()->constrained('batch_stok')->cascadeOnDelete();
            $table->string('pesan', 255);
            // Hari pembuatan (WIB) — kunci anti-duplikat harian
            $table->date('tanggal');
            // NULL = belum tampil di ponsel (FR-24)
            $table->dateTime('ditampilkan_pada')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->unique(['jenis', 'barang_id', 'batch_id', 'tanggal'], 'uq_notifikasi_harian');
            $table->index('ditampilkan_pada', 'idx_notifikasi_belum_tampil');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi');
    }
};
