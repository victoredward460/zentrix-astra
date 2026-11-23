<?php

namespace App\Services\SafeNavigation;

class UrlReputationProviderRegistry
{
    /**
     * @param  list<UrlReputationProvider>  $providers
     */
    public function __construct(private readonly array $providers = []) {}

    /**
     * @return array<string, UrlReputationProvider>
     */
    public function keyed(): array
    {
        $providers = [];

        foreach ($this->providers as $provider) {
            $providers[$provider->key()] = $provider;
        }

        return $providers;
    }
}
