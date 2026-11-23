<?php

namespace App\Models;

use App\Enums\ScanCheckOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanCheckResult extends Model
{
    protected $fillable = [
        'check_key',
        'outcome',
        'summary',
        'evidence',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => ScanCheckOutcome::class,
            'evidence' => 'array',
        ];
    }

    public function scanRun(): BelongsTo
    {
        return $this->belongsTo(ScanRun::class);
    }
}
