<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $table = 'services';

    protected $fillable = [
        'name',
        'description',
        'price',
        'duration',
        'image_path'
    ];

    // Schema has only created_at
    public $timestamps = false;

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}

