<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Round extends Model
{
    protected $fillable = ['round_id', 'name'];
    public function pricings()
    {
        return $this->hasMany(RoundPricing::class);
    }
    public function manifests()
    {
        return $this->hasMany(Manifest::class);
    }
}
