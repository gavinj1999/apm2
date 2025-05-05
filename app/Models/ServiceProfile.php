<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceProfile extends Model
{
    protected $fillable = [
        'user_id',
        'round_id',
        'fuel_cost_per_unit',
        'distance_unit',
        'distance_home_to_work', // New field
        'distance_work_to_start', // New field
        'distance_end_to_home', // New field
        'loading_time_cost_per_hour',
        'loading_time_hours',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function round()
    {
        return $this->belongsTo(Round::class);
    }
    public function locations()
    {
        return $this->hasMany(Location::class);
    }
}
