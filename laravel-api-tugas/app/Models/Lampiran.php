<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lampiran extends Model
{
    protected $table = 'lampiran';

    protected $fillable = ['batch_id', 'jenis', 'path'];

    protected $appends = ['file'];

    public function batch()
    {
        return $this->belongsTo(BatchStok::class, 'batch_id');
    }

    /**
     * Akses gambar hanya lewat endpoint terotorisasi, bukan folder publik (FR-14).
     */
    public function getFileAttribute(): string
    {
        return route('lampiran.file', ['lampiran' => $this->id]);
    }

    /**
     * Ekstensi berkas hasil path tersimpan sendiri (nama UUID, NFR-01).
     */
    public function ekstensi(): string
    {
        $ekstensi = pathinfo((string) $this->path, PATHINFO_EXTENSION);

        return $ekstensi !== '' ? $ekstensi : 'jpg';
    }
}
