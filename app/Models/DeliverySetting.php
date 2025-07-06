<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliverySetting extends Model
{
    protected $table = 'delivery_settings';

    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'float',
    ];
}