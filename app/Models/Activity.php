<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    protected $fillable = [
        'datetime',
        'latitude',
        'longitude',
        'activity',
        'is_manual',
    ];

    protected $casts = [
        'datetime' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_manual' => 'boolean',
    ];

    /**
     * Get the activity type associated with this activity.
     */
    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class, 'activity', 'name');
    }

    /**
     * Get the location associated with this activity (if fixed).
     */
    public function location()
    {
        return $this->belongsTo(Location::class, 'latitude', 'latitude')
                    ->whereColumn('locations.longitude', '=', 'activities.longitude');
    }

    /**
     * Get the distances where this activity is the starting point.
     */
    public function distancesFrom()
    {
        return $this->hasMany(ActivityDistance::class, 'activity_from_id');
    }

    /**
     * Get the distances where this activity is the ending point.
     */
    public function distancesTo()
    {
        return $this->hasMany(ActivityDistance::class, 'activity_to_id');
    }
}