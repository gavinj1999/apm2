<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityDistance extends Model
{
    protected $fillable = [
        'date',
        'segment',
        'distance',
        'unit',
        'activity_from_id',
        'activity_to_id',
    ];

    protected $casts = [
        'date' => 'date',
        'distance' => 'float',
    ];

    public function activityFrom()
    {
        return $this->belongsTo(Activity::class, 'activity_from_id');
    }

    public function activityTo()
    {
        return $this->belongsTo(Activity::class, 'activity_to_id');
    }
}