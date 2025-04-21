<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManifestItem extends Model
{
    /** @use HasFactory<\Database\Factories\ManifestItemFactory> */
    use HasFactory;

    public function parcelType()
    {
        return $this->belongsTo(ParcelType::class);
    }
    protected $fillable = ['manifest_id', 'tracking_number', 'recipient_name', 'delivery_address', 'status', 'parcel_type_id'];

}
