<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->comment('Catatan notifikasi yang diambil Android via API (FR-24)');
            $table->id();
            $table->enum('jenis', ['Mendekati Kedaluwarsa', 'Kedaluwarsa', 'Stok Menipis']);

            $table->foreignId('barang_id')->constrained('barang')->cascadeOnDelete();

            $table->foreignId('batch_id')->nullable()->constrained('batch_stok')->cascadeOnDelete();
            $table->string('pesan', 255);

            $table->date('tanggal');

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
