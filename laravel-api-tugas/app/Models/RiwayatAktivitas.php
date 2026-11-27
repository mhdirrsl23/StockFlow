<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiwayatAktivitas extends Model
{
    public $timestamps = false;

    protected $table = 'riwayat_aktivitas';

    protected $fillable = ['user_id', 'aksi', 'entitas', 'entitas_id', 'keterangan', 'waktu'];

    protected $casts = ['waktu' => 'datetime'];

    public function pengguna()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeAksi($query, ?string $aksi)
    {
        return $aksi ? $query->where('aksi', $aksi) : $query;
    }
}
