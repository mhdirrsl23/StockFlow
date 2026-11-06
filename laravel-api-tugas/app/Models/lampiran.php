<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class lampiran extends Model
{
     protected $table = 'lampiran';
     protected $timestamps = true;
     protected $fillable =['id','batch_id','jenis','path','created_at','updated_at'];
}
