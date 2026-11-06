<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pengaturan extends Model
{
    protected $table = 'pengaturan';
         protected $timestamps = true;
         protected $fillable =['kunci','nilai','updated_at'];
}
