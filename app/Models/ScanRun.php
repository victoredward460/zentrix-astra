<?php

namespace App\Models;

use App\Enums\ScanStatus;
use App\Enums\ScanType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScanRun extends Model
{
    protected $fillable = [
        'user_id',
        'security_asset_id',
        'scan_type',
        'status',
        'requested_at',
        'started_at',
        'finished_at',
        'failure_code',
    ];

    protected function casts(): array
    {
        return [
            'status' => ScanStatus::class,
            'scan_type' => ScanType::class,
            'requested_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function securityAsset(): BelongsTo
    {
        return $this->belongsTo(SecurityAsset::class);
    }

    public function checkResults(): HasMany
    {
        return $this->hasMany(ScanCheckResult::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(SecurityFinding::class);
    }
}
