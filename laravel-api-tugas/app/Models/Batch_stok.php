<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Batch_stock extends Model
{
     protected $table = 'batch_stok';
     protected $timestamps = true;
     protected $fillable =['id','barang_id','nomor_batch','jumlah_awal','jumlah_sisa','tanggal_kadaluwarsa','status','status_penanganan','catatan_penanganan','created_by','deleted_by','deleted_at','deleted_key','created_at','updated_at'];
}
