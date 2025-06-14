<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasFactory;

        protected $fillable = ['datetime', 'latitude', 'longitude', 'activity', 'is_manual'];
    protected $dates = ['datetime'];

    protected $casts = [
        'datetime' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];
}
