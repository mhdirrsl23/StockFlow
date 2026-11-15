<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * pengaturan — FR-25; Q-05.
     * Penyimpanan key-value; kunci utama: ambang_peringatan_hari.
     */
    public function up(): void
    {
        Schema::create('pengaturan', function (Blueprint $table) {
            $table->comment('Pengaturan aplikasi, mis. ambang peringatan kedaluwarsa (hari)');
            $table->string('kunci', 100)->primary();
            $table->string('nilai', 255);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
    }
};
