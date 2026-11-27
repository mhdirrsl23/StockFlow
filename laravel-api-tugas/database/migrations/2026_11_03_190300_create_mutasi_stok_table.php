<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutasi_stok', function (Blueprint $table) {
            $table->comment('Riwayat mutasi stok per batch (audit, tidak diubah lewat aplikasi)');
            $table->id();
            $table->foreignId('batch_id')->constrained('batch_stok')->cascadeOnDelete();
            $table->enum('jenis', ['masuk', 'keluar', 'penyesuaian', 'penanganan']);

            $table->integer('jumlah');
            $table->string('catatan', 255)->nullable()->comment('Alasan penyesuaian / penanganan');

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
