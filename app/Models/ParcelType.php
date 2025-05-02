<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParcelType extends Model
{
    protected $fillable = ['name', 'round_id','sort_order'];

    public function round()
    {
        return $this->belongsTo(Round::class);
    }

    public function roundPricings()
    {
        return $this->hasMany(RoundPricing::class);
    }
}

