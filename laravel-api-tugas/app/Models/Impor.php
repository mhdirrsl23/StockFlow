<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Impor extends Model
{
    protected $table = 'impor';

    protected $fillable = [
        'user_id', 'nama_berkas', 'status', 'token', 'jumlah_baris', 'berhasil',
        'gagal', 'baris_valid', 'kesalahan', 'kedaluwarsa_token', 'waktu_selesai',
    ];

    protected $casts = [
        'baris_valid' => 'array',
        'kesalahan' => 'array',
        'kedaluwarsa_token' => 'datetime',
        'waktu_selesai' => 'datetime',
    ];

    public function pengguna()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function masihValid(): bool
    {
        return $this->status === 'menunggu_konfirmasi'
            && $this->kedaluwarsa_token !== null
            && now()->lessThan($this->kedaluwarsa_token);
    }
}
