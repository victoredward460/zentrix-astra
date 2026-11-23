<?php

namespace App\Models;

use App\Enums\AgentMessageRole;
use App\Enums\AgentMessageStatus;
use App\Enums\AgentProviderFailure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMessage extends Model
{
    protected $fillable = [
        'user_id',
        'role',
        'status',
        'content',
        'provider',
        'model',
        'error_code',
        'prompt_tokens',
        'completion_tokens',
    ];

    protected function casts(): array
    {
        return [
            'role' => AgentMessageRole::class,
            'status' => AgentMessageStatus::class,
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(AgentThread::class, 'thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function failureMessage(): ?string
    {
        return AgentProviderFailure::tryFrom((string) $this->error_code)?->userMessage();
    }
}
