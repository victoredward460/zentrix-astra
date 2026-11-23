<?php

namespace App\Models;

use App\Enums\UrlThreatClassification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SafeNavigationCheck extends Model
{
    protected $fillable = [
        'user_id',
        'url',
        'classification',
        'provider_results',
        'assessed_at',
    ];

    protected function casts(): array
    {
        return [
            'classification' => UrlThreatClassification::class,
            'provider_results' => 'array',
            'assessed_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
