<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    protected $fillable = ['nama', 'username', 'password', 'peran', 'aktif'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'aktif' => 'boolean',
        ];
    }

    public function isPemilik(): bool
    {
        return $this->peran === 'pemilik';
    }

    public function barangDibuat()
    {
        return $this->hasMany(Barang::class, 'created_by');
    }

    public function batchDibuat()
    {
        return $this->hasMany(BatchStok::class, 'created_by');
    }

    public function riwayatAktivitas()
    {
        return $this->hasMany(RiwayatAktivitas::class, 'user_id');
    }

    /**
     * Nama tampilan yang dipakai pesan personal (FR-02).
     */
    protected function namaPendek(): Attribute
    {
        return Attribute::get(fn ($value, $attributes) => str($attributes['nama'] ?? '')->before(' ')->toString() ?: ($attributes['username'] ?? ''));
    }
}
