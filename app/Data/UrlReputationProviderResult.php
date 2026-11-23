<?php

namespace App\Data;

use App\Enums\UrlThreatClassification;
use Carbon\CarbonImmutable;

final readonly class UrlReputationProviderResult
{
    /**
     * @param  list<string>  $threatTypes
     */
    public function __construct(
        public string $providerKey,
        public string $providerName,
        public UrlThreatClassification $classification,
        public array $threatTypes = [],
        public ?string $evidence = null,
        public ?CarbonImmutable $cacheExpiresAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_key' => $this->providerKey,
            'provider_name' => $this->providerName,
            'classification' => $this->classification->value,
            'threat_types' => array_slice(array_map(
                fn (string $type): string => mb_substr($type, 0, 64),
                $this->threatTypes,
            ), 0, 20),
            'evidence' => $this->evidence === null ? null : mb_substr($this->evidence, 0, 300),
            'cache_expires_at' => $this->cacheExpiresAt?->toIso8601String(),
        ];
    }
}
