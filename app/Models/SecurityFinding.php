<?php

namespace App\Models;

use App\Enums\FindingVerificationState;
use App\Enums\SecurityFindingSeverity;
use App\Enums\SecurityFindingState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityFinding extends Model
{
    protected $fillable = [
        'user_id',
        'rule_key',
        'title',
        'description',
        'recommended_action',
        'severity',
        'verification_state',
        'state',
        'evidence',
        'detected_at',
    ];

    protected function casts(): array
    {
        return [
            'severity' => SecurityFindingSeverity::class,
            'verification_state' => FindingVerificationState::class,
            'state' => SecurityFindingState::class,
            'evidence' => 'array',
            'detected_at' => 'immutable_datetime',
        ];
    }

    public function scanRun(): BelongsTo
    {
        return $this->belongsTo(ScanRun::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
