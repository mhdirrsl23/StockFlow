<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lampiran extends Model
{
    protected $fillable = ['batch_id', 'jenis', 'path'];

    protected $appends = ['file'];

    public function batch()
    {
        return $this->belongsTo(BatchStok::class, 'batch_id');
    }

    /**
     * Wayar gambar hanya lewat endpoint terotorisasi, bukan folder publik (FR-14).
     */
    public function getFileAttribute(): string
    {
        return route('lampiran.file', ['lampiran' => $this->id]);
    }
}
