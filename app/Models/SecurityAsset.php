<?php

namespace App\Models;

use App\Enums\AssetVerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecurityAsset extends Model
{
    protected $fillable = [
        'hostname',
        'authorization_attested_at',
    ];

    protected function casts(): array
    {
        return [
            'verification_status' => AssetVerificationStatus::class,
            'authorization_attested_at' => 'immutable_datetime',
            'verification_expires_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scanRuns(): HasMany
    {
        return $this->hasMany(ScanRun::class);
    }
}
