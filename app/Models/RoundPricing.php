<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RoundPricing extends Model
{
    protected $fillable = ['round_id', 'parcel_type_id', 'price'];
    public function round()
    {
        return $this->belongsTo(Round::class);
    }
    public function parcelType()
    {
        return $this->belongsTo(ParcelType::class);
    }
}
