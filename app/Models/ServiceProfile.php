<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceProfile extends Model
{
    protected $fillable = [
        'user_id',
        'fuel_cost_per_unit',
        'distance_unit',
        'distance_home_to_depot',
        'distance_depot_to_start',
        'distance_end_to_home',
        'loading_time_minutes',
        'loading_time_cost_per_hour',
        'total_fuel_cost',
        'total_loading_cost',
        'total_cost',
    ];

    public function locations()
    {
        return $this->hasMany(Location::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}