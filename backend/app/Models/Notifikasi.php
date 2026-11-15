<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $jenis
 * @property int $barang_id
 * @property int|null $batch_id
 * @property string $pesan
 * @property string $tanggal
 * @property string|null $ditampilkan_pada
 * @property string|null $created_at
 *
 * @mixin Eloquent
 */
class Notifikasi extends Model
{
    /**
     * Kolom created_at punya default CURRENT_TIMESTAMP; tidak ada updated_at.
     */
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

    /**
     * Anti-duplikat FR-23: satu baris per jenis per barang/batch per hari (WIB).
     */
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
