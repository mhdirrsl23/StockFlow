<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mutasi_stok extends Model
{
     protected $table = 'mutasi_stok';
     protected $timestamps = false;
     protected $fillable =['id','batch_id','jenis','jumlah','catatan','user_id','waktu'];
}

