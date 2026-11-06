<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
     protected $table = 'notifikasi';
     protected $timestamps = true;
     protected $fillable =['id','jenis','barang_id','batch_id','pesan','tanggal','ditampilkan_pada','created_at'];
}

