<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * riwayat_aktivitas — FR-31; NFR-08.
     * Audit log seluruh aksi penting. Tidak ada fitur ubah/hapus lewat aplikasi.
     */
    public function up(): void
    {
        Schema::create('riwayat_aktivitas', function (Blueprint $table) {
            $table->comment('Riwayat aktivitas pengguna (immutable, NFR-08)');
            $table->id();
            // NULL bila akun pernah terhapus; keterangan tetap utuh
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('aksi', [
                'login', 'logout', 'tambah', 'ubah', 'hapus', 'hapus_massal', 'impor',
                'pengeluaran_stok', 'penanganan_kedaluwarsa', 'ubah_pengaturan',
            ]);
            // barang | batch_stok | users | pengaturan
            $table->string('entitas', 50)->nullable();
            $table->unsignedBigInteger('entitas_id')->nullable();
            // Contoh hapus massal: "Menghapus 3 batch" (5.9 C)
            $table->text('keterangan')->nullable();
            $table->timestamp('waktu')->useCurrent();

            // Staf melihat riwayat miliknya saja (FR-31)
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
