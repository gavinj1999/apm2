<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ManifestSummary extends Model
{
    protected $fillable = ['manifest_id', 'parcel_type_id', 'manifested', 're_manifested', 'carried_forward'];
    public function manifest()
    {
        return $this->belongsTo(Manifest::class);
    }
    public function parcelType()
    {
        return $this->belongsTo(ParcelType::class);
    }
        public function parcel_type()
    {
        return $this->belongsTo(ParcelType::class);
    }

    public function getTotalParcelsAttribute()
    {
        return ($this->manifested ?? 0) + ($this->re_manifested ?? 0) + ($this->carried_forward ?? 0);
    }

}
