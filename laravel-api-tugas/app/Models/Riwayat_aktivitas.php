<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Riwayat_aktivitas extends Model
{
    protected $table = 'riwayat_aktivitas';
    protected $timestamps = false;
    protected $fillable =['id','user_id','aksi','entitas','entitas_id','keterangan','waktu'];
}
