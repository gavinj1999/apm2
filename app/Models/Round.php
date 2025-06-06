<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Round extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'created_at',
        'updated_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roundPricings(): HasMany
    {
        return $this->hasMany(RoundPricing::class);
    }

    public function manifests(): HasMany
    {
        return $this->hasMany(Manifest::class);
    }
    public function pricings(): HasMany
    {
        return $this->hasMany(RoundPricing::class, 'round_id', 'id');
    }

    public function parcelTypes()
    {
        return $this->belongsToMany(ParcelType::class, 'round_pricings')
            ->withPivot('price');
    }

    public function serviceProfiles()
    {
        return $this->hasMany(ServiceProfile::class);
    }

}
