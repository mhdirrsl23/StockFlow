<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_berkas', 255);
            $table->enum('status', ['menunggu_konfirmasi', 'selesai', 'dibatalkan', 'kedaluwarsa'])
                ->default('menunggu_konfirmasi');
            $table->string('token', 64)->unique('uq_impor_token');
            $table->unsignedInteger('jumlah_baris')->default(0);
            $table->unsignedInteger('berhasil')->default(0);
            $table->unsignedInteger('gagal')->default(0);

            $table->json('baris_valid')->nullable();
            $table->json('kesalahan')->nullable();
            $table->dateTime('kedaluwarsa_token')->nullable();
            $table->dateTime('waktu_selesai')->nullable();
            $table->timestamps();

            $table->index(['status', 'user_id'], 'idx_impor_status_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impor');
    }
};
