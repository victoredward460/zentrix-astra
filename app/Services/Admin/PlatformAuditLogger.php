<?php

namespace App\Services\Admin;

use App\Models\PlatformAuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class PlatformAuditLogger
{
    /**
     * @param  array<string, bool|int|string>  $metadata
     */
    public function record(
        User $actor,
        string $eventKey,
        ?Model $subject = null,
        array $metadata = [],
    ): PlatformAuditEvent {
        if (! preg_match('/\A[a-z][a-z0-9._-]{0,99}\z/', $eventKey)) {
            throw new InvalidArgumentException('Invalid audit event key.');
        }

        foreach ($metadata as $key => $value) {
            $isAllowedValue = match ($key) {
                'old_role', 'new_role' => is_string($value) && in_array($value, ['user', 'admin'], true),
                'old_active', 'new_active' => is_bool($value),
                'old_published', 'new_published' => is_bool($value),
                'feature_key' => is_string($value) && in_array($value, [
                    'public-registration',
                    'scanner',
                    'website-auditor',
                    'file-check',
                    'ai-agent',
                    'academy',
                ], true),
                'new_enabled' => is_bool($value),
                'successful' => is_bool($value),
                'indicator_count' => is_int($value) && $value >= 0,
                'content_type' => is_string($value)
                    && in_array($value, ['category', 'course', 'lesson', 'quiz', 'question'], true),
                default => false,
            };

            if (! $isAllowedValue || (is_string($value) && mb_strlen($value) > 80)) {
                throw new InvalidArgumentException('Audit metadata contains an unsupported field.');
            }
        }

        return PlatformAuditEvent::query()->create([
            'actor_id' => $actor->getKey(),
            'event_key' => $eventKey,
            'subject_type' => $subject === null ? null : class_basename($subject),
            'subject_id' => $subject === null ? null : (string) $subject->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }
}
