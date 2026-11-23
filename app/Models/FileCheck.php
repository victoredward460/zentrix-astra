<?php

namespace App\Models;

use App\Enums\FileCheckStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileCheck extends Model
{
    protected $fillable = [
        'user_id',
        'storage_key',
        'original_name',
        'size_bytes',
        'mime_type',
        'sha256',
        'vt_status',
        'vt_malicious',
        'vt_suspicious',
        'vt_harmless',
        'vt_undetected',
        'vt_error_code',
        'vt_retry_after_seconds',
        'vt_last_checked_at',
        'status',
        'failure_code',
        'processed_at',
    ];

    protected $hidden = [
        'storage_key',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'status' => FileCheckStatus::class,
            'processed_at' => 'immutable_datetime',
            'vt_malicious' => 'integer',
            'vt_suspicious' => 'integer',
            'vt_harmless' => 'integer',
            'vt_undetected' => 'integer',
            'vt_retry_after_seconds' => 'integer',
            'vt_last_checked_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
