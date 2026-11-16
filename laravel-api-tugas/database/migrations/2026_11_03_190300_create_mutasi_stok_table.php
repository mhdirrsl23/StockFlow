<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mutasi_stok — FR-11, FR-13, FR-22, FR-26, FR-32, FR-35; AB-10.
     * jenis: masuk | keluar | penyesuaian | penanganan.
     * FEFO: satu pengeluaran bisa menghasilkan beberapa baris keluar,
     * satu per batch yang dialokasikan (kriteria 5.9 A).
     */
    public function up(): void
    {
        Schema::create('mutasi_stok', function (Blueprint $table) {
            $table->comment('Riwayat mutasi stok per batch (audit, tidak diubah lewat aplikasi)');
            $table->id();
            $table->foreignId('batch_id')->constrained('batch_stok')->cascadeOnDelete();
            $table->enum('jenis', ['masuk', 'keluar', 'penyesuaian', 'penanganan']);
            // Positif untuk masuk/keluar/penanganan; penyesuaian = selisih, boleh negatif (FR-13)
            $table->integer('jumlah');
            $table->string('catatan', 255)->nullable()->comment('Alasan penyesuaian / penanganan');
            // Pengguna pelaku (AB-10)
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('waktu')->useCurrent();

            $table->index('batch_id', 'idx_mutasi_batch');
            $table->index('waktu', 'idx_mutasi_waktu')->comment('Filter periode laporan FR-35');
            $table->index('jenis', 'idx_mutasi_jenis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutasi_stok');
    }
};
