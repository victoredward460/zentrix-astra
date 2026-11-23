<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformFeatureSetting extends Model
{
    protected $fillable = [
        'feature_key',
        'enabled',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
