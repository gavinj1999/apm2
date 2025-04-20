<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManifestItem extends Model
{
    /** @use HasFactory<\Database\Factories\ManifestItemFactory> */
    use HasFactory;

    protected $fillable = ['manifest_id', 'parcel_type', 'manifested', 're_manifested', 'carried_forward'];

    public function manifest()
    {
        return $this->belongsTo(Manifest::class);
    }

}
