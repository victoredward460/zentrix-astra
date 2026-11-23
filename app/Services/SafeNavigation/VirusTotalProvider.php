<?php

namespace App\Services\SafeNavigation;

use App\Data\UrlReputationProviderResult;
use App\Enums\UrlThreatClassification;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class VirusTotalProvider implements UrlReputationProvider
{
    private const ENDPOINT = 'https://www.virustotal.com/api/v3/urls/';
    private const FILE_ENDPOINT = 'https://www.virustotal.com/api/v3/files/';
    private const URL_CACHE_SECONDS = 900;
    private const FILE_CACHE_SECONDS = 3600;

    public function key(): string
    {
        return 'virustotal';
    }

    public function isConfigured(): bool
    {
        return is_string(config('safe_navigation.providers.virustotal.credential'))
            && trim((string) config('safe_navigation.providers.virustotal.credential')) !== '';
    }

    public function assess(string $normalizedUrl): UrlReputationProviderResult
    {
        if (! $this->isConfigured()) {
            throw new UrlReputationProviderException;
        }

        $urlId = rtrim(strtr(base64_encode($normalizedUrl), '+/', '-_'), '=');

        $cacheKey = 'virustotal:url:'.hash('sha256', $normalizedUrl);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return new UrlReputationProviderResult(
                providerKey: $this->key(),
                providerName: 'VirusTotal',
                classification: UrlThreatClassification::tryFrom((string) ($cached['classification'] ?? ''))
                    ?? UrlThreatClassification::UnableToVerify,
                threatTypes: is_array($cached['threat_types'] ?? null) ? $cached['threat_types'] : [],
                evidence: is_string($cached['evidence'] ?? null) ? $cached['evidence'] : null,
                cacheExpiresAt: null,
            );
        }

        try {
            $response = $this->request()->get(self::ENDPOINT.rawurlencode($urlId));
        } catch (ConnectionException $exception) {
            throw new UrlReputationProviderException('connection_failed');
        }

        if ($response->status() === 429) {
            throw new UrlReputationProviderException(
                'rate_limited',
                $this->retryAfterSeconds($response->header('Retry-After')),
            );
        }

        if ($response->status() === 401) {
            throw new UrlReputationProviderException('invalid_api_key');
        }

        if ($response->status() === 403) {
            throw new UrlReputationProviderException('forbidden');
        }

        if ($response->status() === 404) {
            $submitted = $this->submitUrl($normalizedUrl);
            if ($submitted === null) {
                throw new UrlReputationProviderException('not_found');
            }
            $response = $submitted;
        }

        if (! $response->successful()) {
            throw new UrlReputationProviderException('http_status_'.$response->status());
        }

        $statistics = $response->json('data.attributes.last_analysis_stats');

        if (! is_array($statistics)) {
            throw new UrlReputationProviderException('invalid_response');
        }

        $malicious = $this->nonNegativeCount($statistics['malicious'] ?? null);
        $suspicious = $this->nonNegativeCount($statistics['suspicious'] ?? null);

        if ($malicious === null || $suspicious === null) {
            throw new UrlReputationProviderException('invalid_response');
        }

        $classification = $malicious > 0
            ? UrlThreatClassification::Malicious
            : ($suspicious > 0
                ? UrlThreatClassification::Suspicious
                : UrlThreatClassification::NoThreatDetected);

        $result = new UrlReputationProviderResult(
            providerKey: $this->key(),
            providerName: 'VirusTotal',
            classification: $classification,
            threatTypes: $malicious > 0 ? ['MALICIOUS_ANALYSIS'] : ($suspicious > 0 ? ['SUSPICIOUS_ANALYSIS'] : []),
            evidence: "VirusTotal reported {$malicious} malicious and {$suspicious} suspicious detections.",
        );

        Cache::put($cacheKey, $result->toArray(), now()->addSeconds(self::URL_CACHE_SECONDS));

        return $result;
    }

    /**
     * @return array{
     *   status: 'found'|'not_found',
     *   classification: string,
     *   malicious: int,
     *   suspicious: int,
     *   harmless: int,
     *   undetected: int,
     *   evidence: string
     * }
     */
    public function lookupFileHash(string $sha256): array
    {
        if (! preg_match('/\A[a-fA-F0-9]{64}\z/', $sha256)) {
            throw new UrlReputationProviderException('invalid_hash');
        }

        $sha256 = strtolower($sha256);
        $cacheKey = 'virustotal:file:'.$sha256;

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = $this->request()->get(self::FILE_ENDPOINT.$sha256);
        } catch (ConnectionException) {
            throw new UrlReputationProviderException('connection_failed');
        }

        if ($response->status() === 429) {
            throw new UrlReputationProviderException(
                'rate_limited',
                $this->retryAfterSeconds($response->header('Retry-After')),
            );
        }

        if ($response->status() === 401) {
            throw new UrlReputationProviderException('invalid_api_key');
        }

        if ($response->status() === 403) {
            throw new UrlReputationProviderException('forbidden');
        }

        if ($response->status() === 404) {
            $result = [
                'status' => 'not_found',
                'classification' => 'unknown',
                'malicious' => 0,
                'suspicious' => 0,
                'harmless' => 0,
                'undetected' => 0,
                'evidence' => 'VirusTotal had no report for this SHA-256 hash.',
            ];
            Cache::put($cacheKey, $result, now()->addSeconds(self::FILE_CACHE_SECONDS));
            return $result;
        }

        if (! $response->successful()) {
            throw new UrlReputationProviderException('http_status_'.$response->status());
        }

        $statistics = $response->json('data.attributes.last_analysis_stats');
        if (! is_array($statistics)) {
            throw new UrlReputationProviderException('invalid_response');
        }

        $malicious = $this->nonNegativeCount($statistics['malicious'] ?? null);
        $suspicious = $this->nonNegativeCount($statistics['suspicious'] ?? null);
        $harmless = $this->nonNegativeCount($statistics['harmless'] ?? null);
        $undetected = $this->nonNegativeCount($statistics['undetected'] ?? null);

        if ($malicious === null || $suspicious === null || $harmless === null || $undetected === null) {
            throw new UrlReputationProviderException('invalid_response');
        }

        $classification = $malicious > 0
            ? 'malicious'
            : ($suspicious > 0 ? 'suspicious' : 'no_threat_detected');

        $result = [
            'status' => 'found',
            'classification' => $classification,
            'malicious' => $malicious,
            'suspicious' => $suspicious,
            'harmless' => $harmless,
            'undetected' => $undetected,
            'evidence' => "VirusTotal reported {$malicious} malicious, {$suspicious} suspicious, {$harmless} harmless, and {$undetected} undetected engine results.",
        ];
        Cache::put($cacheKey, $result, now()->addSeconds(self::FILE_CACHE_SECONDS));
        return $result;
    }

    private function nonNegativeCount(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }

    private function request()
    {
        return Http::acceptJson()
            ->withHeaders(['x-apikey' => (string) config('safe_navigation.providers.virustotal.credential')])
            ->connectTimeout(3)
            ->timeout(8)
            ->withoutRedirecting();
    }

    private function retryAfterSeconds(?string $value): ?int
    {
        if ($value === null || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        $seconds = (int) $value;
        return $seconds > 0 ? min($seconds, 3600) : null;
    }

    private function submitUrl(string $normalizedUrl)
    {
        try {
            $submission = $this->request()
                ->asForm()
                ->post('https://www.virustotal.com/api/v3/urls', [
                    'url' => $normalizedUrl,
                ]);
        } catch (ConnectionException) {
            throw new UrlReputationProviderException('connection_failed');
        }

        if ($submission->status() === 429) {
            throw new UrlReputationProviderException(
                'rate_limited',
                $this->retryAfterSeconds($submission->header('Retry-After')),
            );
        }

        if ($submission->status() === 401) {
            throw new UrlReputationProviderException('invalid_api_key');
        }

        if ($submission->status() === 403) {
            throw new UrlReputationProviderException('forbidden');
        }

        if (! in_array($submission->status(), [200, 201, 202], true)) {
            return null;
        }

        $analysisId = $submission->json('data.id');
        if (! is_string($analysisId) || trim($analysisId) === '') {
            return null;
        }

        try {
            $analysis = $this->request()->get('https://www.virustotal.com/api/v3/analyses/'.rawurlencode($analysisId));
        } catch (ConnectionException) {
            throw new UrlReputationProviderException('connection_failed');
        }

        if (! $analysis->successful()) {
            return null;
        }

        $stats = $analysis->json('data.attributes.stats');
        if (! is_array($stats)) {
            return null;
        }

        $malicious = $this->nonNegativeCount($stats['malicious'] ?? null);
        $suspicious = $this->nonNegativeCount($stats['suspicious'] ?? null);
        $harmless = $this->nonNegativeCount($stats['harmless'] ?? null);
        if ($malicious === null || $suspicious === null || $harmless === null) {
            return null;
        }

        return Http::response([
            'data' => [
                'attributes' => [
                    'last_analysis_stats' => [
                        'malicious' => $malicious,
                        'suspicious' => $suspicious,
                        'harmless' => $harmless,
                    ],
                ],
            ],
        ], 200);
    }
}
