<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manifest extends Model
{
    protected $fillable = ['manifest_number', 'delivery_date', 'status', 'user_id', 'round_id'];

    public function round()
    {
        return $this->belongsTo(Round::class);
    }

    public function summaries()
    {
        return $this->hasMany(ManifestSummary::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function quantities()
    {
        return $this->hasMany(ManifestSummary::class);
    }
}
