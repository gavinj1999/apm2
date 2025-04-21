<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class ManifestAdditionalMetric extends Model
{
    protected $fillable = [
        'manifest_id',
        'next_day_manifested',
        'next_day_re_manifested',
        'next_day_carried_forward',
        'pod_signature_manifested',
        'pod_signature_re_manifested',
        'pod_signature_carried_forward'
    ];
    public function manifest()
    {
        return $this->belongsTo(Manifest::class);
    }
}
