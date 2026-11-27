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

    public function getFileAttribute(): string
    {
        return route('lampiran.file', ['lampiran' => $this->id]);
    }

    public function ekstensi(): string
    {
        $ekstensi = pathinfo((string) $this->path, PATHINFO_EXTENSION);

        return $ekstensi !== '' ? $ekstensi : 'jpg';
    }
}
