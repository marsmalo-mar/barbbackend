<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barber extends Model
{
    protected $table = 'barbers';

    protected $fillable = [
        'name',
        'email',
        'specialty',
        'bio',
        'phone',
        'image_path'
    ];

    public $timestamps = true;

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}

