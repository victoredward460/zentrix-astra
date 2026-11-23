<?php

namespace App\Services\SafeNavigation;

use App\Data\UrlReputationProviderResult;
use App\Enums\UrlThreatClassification;
use App\Models\SafeNavigationCheck;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class SafeNavigationAssessmentService
{
    public function __construct(
        private readonly UrlReputationProviderRegistry $providerRegistry,
        private readonly ProviderStatusService $providerStatusService,
    ) {}

    public function assess(User $administrator, string $normalizedUrl): SafeNavigationCheck
    {
        $registeredProviders = $this->providerRegistry->keyed();
        $results = [];

        foreach ($this->providerStatusService->all() as $providerStatus) {
            $provider = $registeredProviders[$providerStatus['key']] ?? null;

            if ($provider === null || ! $provider->isConfigured()) {
                $results[] = new UrlReputationProviderResult(
                    providerKey: $providerStatus['key'],
                    providerName: $providerStatus['name'],
                    classification: UrlThreatClassification::IntegrationUnavailable,
                    evidence: 'Provider integration is not connected; no lookup was performed.',
                );

                continue;
            }

            try {
                $result = $provider->assess($normalizedUrl);
            } catch (UrlReputationProviderException) {
                Log::warning('Safe Navigation provider lookup failed.', [
                    'provider' => $providerStatus['key'],
                ]);
                $results[] = new UrlReputationProviderResult(
                    providerKey: $providerStatus['key'],
                    providerName: $providerStatus['name'],
                    classification: UrlThreatClassification::UnableToVerify,
                    evidence: 'Provider lookup failed; no reputation result was available.',
                );

                continue;
            }

            if ($result->providerKey !== $providerStatus['key']) {
                throw new \LogicException('The reputation provider returned a result for a different provider.');
            }

            $results[] = $this->currentResult($result);
        }

        return $administrator->safeNavigationChecks()->create([
            'url' => $normalizedUrl,
            'classification' => $this->classify($results),
            'provider_results' => array_map(
                fn (UrlReputationProviderResult $result): array => $result->toArray(),
                $results,
            ),
            'assessed_at' => now(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function resultsForDisplay(SafeNavigationCheck $check): array
    {
        $results = $check->provider_results ?? [];

        return array_map(function (array $result): array {
            $classification = UrlThreatClassification::tryFrom($result['classification'] ?? '');
            $expiresAt = isset($result['cache_expires_at'])
                ? CarbonImmutable::parse($result['cache_expires_at'])
                : null;

            if (
                $classification === null
                || ($expiresAt !== null && $expiresAt->isPast())
            ) {
                return [
                    ...$result,
                    'classification' => UrlThreatClassification::UnableToVerify->value,
                    'evidence' => 'Provider evidence is unavailable or expired; the URL must be checked again.',
                    'threat_types' => [],
                ];
            }

            if ($expiresAt !== null) {
                $result['cache_expires_at'] = $expiresAt->toIso8601String();
            }

            return $result;
        }, $results);
    }

    public function classificationForDisplay(SafeNavigationCheck $check): UrlThreatClassification
    {
        $results = array_map(
            fn (array $result): UrlReputationProviderResult => new UrlReputationProviderResult(
                providerKey: $result['provider_key'],
                providerName: $result['provider_name'],
                classification: UrlThreatClassification::from($result['classification']),
                threatTypes: $result['threat_types'] ?? [],
                evidence: $result['evidence'] ?? null,
                cacheExpiresAt: isset($result['cache_expires_at'])
                    ? CarbonImmutable::parse($result['cache_expires_at'])
                    : null,
            ),
            $this->resultsForDisplay($check),
        );

        return $this->classify($results);
    }

    private function currentResult(UrlReputationProviderResult $result): UrlReputationProviderResult
    {
        if ($result->cacheExpiresAt !== null && $result->cacheExpiresAt->isPast()) {
            return new UrlReputationProviderResult(
                providerKey: $result->providerKey,
                providerName: $result->providerName,
                classification: UrlThreatClassification::UnableToVerify,
                evidence: 'Provider evidence is unavailable or expired; the URL must be checked again.',
            );
        }

        return $result;
    }

    /**
     * @param  list<UrlReputationProviderResult>  $results
     */
    private function classify(array $results): UrlThreatClassification
    {
        foreach ($results as $result) {
            if ($result->classification === UrlThreatClassification::Malicious) {
                return UrlThreatClassification::Malicious;
            }
        }

        foreach ($results as $result) {
            if ($result->classification === UrlThreatClassification::Suspicious) {
                return UrlThreatClassification::Suspicious;
            }
        }

        $availableResults = array_values(array_filter(
            $results,
            fn (UrlReputationProviderResult $result): bool => $result->classification !== UrlThreatClassification::IntegrationUnavailable,
        ));

        if ($availableResults === []) {
            return UrlThreatClassification::IntegrationUnavailable;
        }

        foreach ($availableResults as $result) {
            if ($result->classification === UrlThreatClassification::UnableToVerify) {
                return UrlThreatClassification::UnableToVerify;
            }
        }

        return UrlThreatClassification::NoThreatDetected;
    }
}
