<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ParcelType extends Model
{
    protected $fillable = ['name'];
    public function pricings()
    {
        return $this->hasMany(RoundPricing::class);
    }
}
