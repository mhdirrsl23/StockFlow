<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    public $timestamps = false;

    protected $table = 'notifikasi';

    protected $fillable = ['jenis', 'barang_id', 'batch_id', 'pesan', 'tanggal', 'ditampilkan_pada'];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'ditampilkan_pada' => 'datetime',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'barang_id');
    }

    public function batch()
    {
        return $this->belongsTo(BatchStok::class, 'batch_id');
    }

    public static function catat(string $jenis, int $barangId, ?int $batchId, string $pesan): self
    {
        return static::firstOrCreate(
            [
                'jenis' => $jenis,
                'barang_id' => $barangId,
                'batch_id' => $batchId,
                'tanggal' => today()->toDateString(),
            ],
            ['pesan' => $pesan],
        );
    }

    public function scopeJenis(Builder $query, ?string $jenis): Builder
    {
        return $jenis ? $query->where('jenis', $jenis) : $query;
    }
}
