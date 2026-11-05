<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barang extends Model
{
    protected $table = 'barang';
    protected $timestamps = true;
    protected $fillable = ['id','kode_sku', 'nama','kategori','satuan','stok_minimum','created_by','deleted_by','deleted_at','deleted_key','created_at','updated_at'];
}
