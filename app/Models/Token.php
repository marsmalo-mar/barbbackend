<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    protected $fillable = ['user_id','token','expires_at'];
    public $timestamps = false;
    protected $casts = ['expires_at' => 'datetime'];
}
