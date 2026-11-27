<?php

namespace App\Support;

use App\Models\RiwayatAktivitas;
use App\Models\User;

final class Aktivitas
{
    public static function catat(
        ?User $user,
        string $aksi,
        ?string $entitas = null,
        ?int $entitasId = null,
        ?string $keterangan = null,
    ): RiwayatAktivitas {
        return RiwayatAktivitas::create([
            'user_id' => $user?->id,
            'aksi' => $aksi,
            'entitas' => $entitas,
            'entitas_id' => $entitasId,
            'keterangan' => $keterangan,
            'waktu' => now(),
        ]);
    }
}
