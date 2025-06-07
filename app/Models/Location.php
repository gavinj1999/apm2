<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

protected $fillable = [
        'name',
        'type',
        'latitude',
        'longitude',
        'service_profile_id',
    ];

    public function serviceProfile()
    {
        return $this->belongsTo(ServiceProfile::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
