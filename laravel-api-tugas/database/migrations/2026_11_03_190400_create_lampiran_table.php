<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lampiran', function (Blueprint $table) {
            $table->comment('Lampiran gambar bukti batch dan bukti penanganan kedaluwarsa');
            $table->id();
            $table->foreignId('batch_id')->constrained('batch_stok')->cascadeOnDelete();
            $table->enum('jenis', ['bukti_batch', 'bukti_penanganan']);
            $table->string('path', 255);
            $table->timestamps();

            $table->index('batch_id', 'idx_lampiran_batch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lampiran');
    }
};
