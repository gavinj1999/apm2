<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceProfile extends Model
{
    protected $fillable = [
'round_id',
        'fuel_cost_per_unit',
        'distance_unit',
        'distance_home_to_work',
        'distance_work_to_start',
        'distance_end_to_home',
        'loading_time_cost_per_hour',
        'loading_time_hours',
        'total_fuel_cost',
        'total_loading_cost',
        'total_cost',
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
