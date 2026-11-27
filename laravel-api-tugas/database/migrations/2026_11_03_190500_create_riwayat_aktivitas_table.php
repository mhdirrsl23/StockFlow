<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_aktivitas', function (Blueprint $table) {
            $table->comment('Riwayat aktivitas pengguna (immutable, NFR-08)');
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('aksi', [
                'login', 'logout', 'tambah', 'ubah', 'hapus', 'hapus_massal', 'impor',
                'pengeluaran_stok', 'penanganan_kedaluwarsa', 'ubah_pengaturan',
            ]);

            $table->string('entitas', 50)->nullable();
            $table->unsignedBigInteger('entitas_id')->nullable();

            $table->text('keterangan')->nullable();
            $table->timestamp('waktu')->useCurrent();

            $table->index('user_id', 'idx_riwayat_user');
            $table->index('waktu', 'idx_riwayat_waktu');
            $table->index('aksi', 'idx_riwayat_aksi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_aktivitas');
    }
};
