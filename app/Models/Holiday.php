<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['start_date', 'end_date', 'daily_rate'];

        protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'daily_rate' => 'float', // Cast to float
    ];
}
