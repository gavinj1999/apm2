<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $fillable = ['datetime', 'latitude', 'longitude', 'activity', 'is_manual'];

    protected $casts = [
        'datetime' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_manual' => 'boolean',
    ];

    public function activityType()
    {
        return $this->belongsTo(ActivityType::class, 'activity', 'name');
    }
}
