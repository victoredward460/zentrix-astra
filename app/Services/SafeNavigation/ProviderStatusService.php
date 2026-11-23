<?php

namespace App\Services\SafeNavigation;

class ProviderStatusService
{
    public function __construct(
        private readonly UrlReputationProviderRegistry $providerRegistry,
    ) {}

    /**
     * @return list<array{key: string, name: string, credential_required: bool, credential_variable: string|null, credential_configured: bool, enabled: bool, state: string, state_label: string, feed_status?: array<string, mixed>}>
     */
    public function all(): array
    {
        $providers = config('safe_navigation.providers', []);
        $registeredProviders = $this->providerRegistry->keyed();

        return collect($providers)
            ->map(function (array $provider, string $key) use ($registeredProviders): array {
                $credentialConfigured = filled($provider['credential'] ?? null);
                $adapterAvailable = isset($registeredProviders[$key]);
                $state = ! $credentialConfigured
                    ? 'not_configured'
                    : (! $adapterAvailable ? 'adapter_unavailable' : 'configured_not_tested');

                return [
                    'key' => $key,
                    'name' => $provider['name'],
                    'credential_required' => $provider['credential_required'] ?? true,
                    'credential_variable' => $provider['credential_variable'],
                    'credential_configured' => $credentialConfigured,
                    'enabled' => $credentialConfigured,
                    'state' => $state,
                    'state_label' => match ($state) {
                        'not_configured' => 'Not configured',
                        'adapter_unavailable' => 'Adapter unavailable',
                        default => 'Configured · not tested',
                    },
                ];
            })
            ->values()
            ->all();
    }
}
