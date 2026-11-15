<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MutasiStok extends Model
{
    /**
     * Tabel audit tanpa kolom timestamps Laravel; kolom `waktu` diisi eksplisit.
     */
    public $timestamps = false;

    protected $fillable = ['batch_id', 'jenis', 'jumlah', 'catatan', 'user_id', 'waktu'];

    protected $casts = [
        'waktu' => 'datetime',
        'jumlah' => 'integer',
    ];

    public function batch()
    {
        return $this->belongsTo(BatchStok::class, 'batch_id');
    }

    public function barang()
    {
        return $this->hasOneThrough(Barang::class, BatchStok::class, 'id', 'id', 'batch_id', 'barang_id');
    }

    public function pengguna()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeJenis($query, ?string $jenis)
    {
        return $jenis ? $query->where('jenis', $jenis) : $query;
    }
}
