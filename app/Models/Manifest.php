<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Manifest extends Model
{
    /** @use HasFactory<\Database\Factories\ManifestFactory> */
    use HasFactory;

    protected $fillable = ['manifest_date', 'parsed_manifest_date', 'manifest', 'round'];

    public function items()
    {
        return $this->hasMany(ManifestItem::class);
    }
}
