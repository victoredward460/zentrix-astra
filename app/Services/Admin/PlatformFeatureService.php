<?php

namespace App\Services\Admin;

use App\Models\PlatformFeatureSetting;
use InvalidArgumentException;
use LogicException;

class PlatformFeatureService
{
    private const FEATURES = [
        'public-registration' => [
            'label' => 'Public registration',
            'description' => 'Allow new users to create standard accounts. This never grants administrator access.',
        ],
        'scanner' => [
            'label' => 'Security Scanner',
            'description' => 'Allow ordinary users to register authorized assets and request passive scans.',
        ],
        'website-auditor' => [
            'label' => 'Website Auditor',
            'description' => 'Allow ordinary users to run bounded audits on their verified assets.',
        ],
        'file-check' => [
            'label' => 'File Check',
            'description' => 'Allow ordinary users to record private file metadata and hashes.',
        ],
        'ai-agent' => [
            'label' => 'AI Agent',
            'description' => 'Allow ordinary users to open conversations with the configured AI provider.',
        ],
        'academy' => [
            'label' => 'Cybersecurity Academy',
            'description' => 'Allow ordinary users to access published lessons and quizzes.',
        ],
    ];

    /**
     * @return list<array{key: string, label: string, description: string, enabled: bool, environment_allows_enable: bool}>
     */
    public function all(): array
    {
        $features = [];
        $settings = PlatformFeatureSetting::query()->pluck('enabled', 'feature_key');

        foreach (self::FEATURES as $key => $definition) {
            $environmentAllowsEnable = $key !== 'public-registration'
                || (bool) config('auth.allow_registration');
            $enabled = (bool) $settings->get($key, true) && $environmentAllowsEnable;

            $features[] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'enabled' => $enabled,
                'environment_allows_enable' => $environmentAllowsEnable,
            ];
        }

        return $features;
    }

    public function supports(string $feature): bool
    {
        return array_key_exists($feature, self::FEATURES);
    }

    public function isEnabled(string $feature): bool
    {
        $this->ensureSupported($feature);

        if ($feature === 'public-registration' && ! config('auth.allow_registration')) {
            return false;
        }

        $setting = PlatformFeatureSetting::query()
            ->where('feature_key', $feature)
            ->value('enabled');

        return $setting === null ? true : (bool) $setting;
    }

    public function set(string $feature, bool $enabled, int $actorId): void
    {
        $this->ensureSupported($feature);

        if ($feature === 'public-registration' && $enabled && ! config('auth.allow_registration')) {
            throw new LogicException('Public registration is disabled by the deployment environment.');
        }

        PlatformFeatureSetting::query()->updateOrCreate(
            ['feature_key' => $feature],
            ['enabled' => $enabled, 'updated_by' => $actorId],
        );
    }

    private function ensureSupported(string $feature): void
    {
        if (! $this->supports($feature)) {
            throw new InvalidArgumentException('Unsupported platform feature.');
        }
    }
}
