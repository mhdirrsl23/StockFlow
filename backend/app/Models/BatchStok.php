<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BatchStok extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'barang_id', 'nomor_batch', 'jumlah_awal', 'jumlah_sisa', 'tanggal_kedaluwarsa',
        'status', 'status_penanganan', 'catatan_penanganan',
    ];

    protected $casts = [
        'tanggal_kedaluwarsa' => 'date:Y-m-d',
        'jumlah_awal' => 'integer',
        'jumlah_sisa' => 'integer',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function penghapus()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function mutasi()
    {
        return $this->hasMany(MutasiStok::class, 'batch_id')->orderBy('waktu')->orderBy('id');
    }

    public function lampiran()
    {
        return $this->hasMany(Lampiran::class, 'batch_id');
    }

    /**
     * Batch layak dijual untuk stok keluar normal (FR-07, BR-03):
     * belum kedaluwarsa dan belum Ditangani.
     */
    public function scopeLayakJual(Builder $query): Builder
    {
        return $query->whereDate('tanggal_kedaluwarsa', '>=', today())
            ->whereNotIn('status', ['Kedaluwarsa', 'Ditangani'])
            ->where('jumlah_sisa', '>', 0);
    }

    /**
     * Urutan bawaan FEFO: kedaluwarsa terdekat, lalu dibuat lebih awal (AB-02, BR-04).
     */
    public function scopeFefo(Builder $query): Builder
    {
        return $query->orderBy('tanggal_kedaluwarsa')->orderBy('created_at')->orderBy('id');
    }

    /**
     * sisa_hari (WIB) mengikuti FR-23; dapat negatif.
     */
    public function sisaHari(): int
    {
        return today()->diffInDays($this->tanggal_kedaluwarsa, false);
    }
}
