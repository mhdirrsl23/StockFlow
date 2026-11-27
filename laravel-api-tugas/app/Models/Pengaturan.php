<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pengaturan extends Model
{
    public $timestamps = false;

    protected $table = 'pengaturan';

    protected $primaryKey = 'kunci';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['kunci', 'nilai', 'updated_at'];

    public function scopeAmbil($query, string $kunci, ?string $bawaan = null): ?string
    {
        $baris = $query->where('kunci', $kunci)->first();

        return $baris?->nilai ?? $bawaan;
    }

    public static function nilai(string $kunci, ?string $bawaan = null): ?string
    {
        return static::query()->where('kunci', $kunci)->value('nilai') ?? $bawaan;
    }

    public static function atur(string $kunci, string $nilai): void
    {
        static::updateOrCreate(
            ['kunci' => $kunci],
            ['nilai' => $nilai, 'updated_at' => now()],
        );
    }

    public static function ambangPeringatanHari(): int
    {
        return max(1, (int) static::nilai('ambang_peringatan_hari', '30'));
    }
}
