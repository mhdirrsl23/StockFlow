<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Barang extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['kode_sku', 'nama', 'kategori', 'satuan', 'stok_minimum'];

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function penghapus()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function batches()
    {
        return $this->hasMany(BatchStok::class, 'barang_id');
    }

    /**
     * Batch aktif (belum soft delete) — digunakan untuk otorisasi hapus (BR-07)
     * dan penghitungan stok (AB-01).
     */
    /**
     * Pencarian kode SKU atau nama (FR-17).
     */
    public function scopeCari(Builder $query, ?string $istilah): Builder
    {
        return $istilah
            ? $query->where(function (Builder $q) use ($istilah) {
                $q->where('kode_sku', 'like', "%{$istilah}%")
                    ->orWhere('nama', 'like', "%{$istilah}%");
            })
            : $query;
    }

    public function scopeKategori(Builder $query, ?string $kategori): Builder
    {
        return $kategori ? $query->where('kategori', $kategori) : $query;
    }
}
