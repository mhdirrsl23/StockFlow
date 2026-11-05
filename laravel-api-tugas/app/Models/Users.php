<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Users extends Model
{
    protected $timestamps = true;
    protected $fillable = ['id','nama','username','password','peran','aktif','remember_token','created_at','updated_at']
}
