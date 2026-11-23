<?php

namespace App\Services\SafeNavigation;

use App\Data\UrlReputationProviderResult;

interface UrlReputationProvider
{
    public function key(): string;

    public function isConfigured(): bool;

    public function assess(string $normalizedUrl): UrlReputationProviderResult;
}
