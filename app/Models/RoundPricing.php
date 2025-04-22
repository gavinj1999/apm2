<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundPricing extends Model
{
    protected $fillable = ['round_id', 'parcel_type_id', 'price'];

    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function parcelType(): BelongsTo
    {
        return $this->belongsTo(ParcelType::class);
    }

    /**
     * Customize the JSON serialization to use camelCase for relationships.
     */
    public function toArray()
    {
        $array = parent::toArray();
        if (isset($array['parcel_type'])) {
            $array['parcelType'] = $array['parcel_type'];
            unset($array['parcel_type']);
        }
        return $array;
    }
}
