<?php

namespace App\Services\Scanner\Checks;

use App\Data\ScanCheckResultData;
use App\Data\ScanFindingData;
use App\Enums\ScanCheckOutcome;
use App\Enums\SecurityFindingSeverity;
use App\Services\SafeNavigation\UrlReputationProviderException;
use App\Services\SafeNavigation\VirusTotalProvider;

class VirusTotalHostnameCheck
{
    public function __construct(private readonly VirusTotalProvider $provider) {}

    public function run(string $hostname): ScanCheckResultData
    {
        if (! $this->provider->isConfigured()) {
            return new ScanCheckResultData(
                'virustotal_reputation',
                ScanCheckOutcome::Inconclusive,
                'VirusTotal is not configured; no URL reputation lookup was performed.',
                ['provider_state' => 'not_configured'],
            );
        }

        try {
            $result = $this->provider->assess('https://'.$hostname.'/');
        } catch (UrlReputationProviderException $exception) {
            $summary = match ($exception->reason) {
                'rate_limited' => 'VirusTotal rate limit reached; URL reputation could not be verified.',
                'invalid_api_key' => 'VirusTotal rejected the configured API key; URL reputation could not be verified.',
                'forbidden' => 'VirusTotal denied the URL lookup for the configured account.',
                'not_found' => 'VirusTotal had no URL reputation report for this authorized hostname.',
                'connection_failed' => 'VirusTotal could not be reached; URL reputation could not be verified.',
                default => 'VirusTotal URL reputation could not be verified due to a provider error.',
            };

            return new ScanCheckResultData(
                'virustotal_reputation',
                ScanCheckOutcome::Inconclusive,
                $summary,
                [
                    'provider_state' => 'unavailable',
                    'error_code' => $exception->reason,
                    'retry_after_seconds' => $exception->retryAfterSeconds,
                ],
            );
        }

        $evidence = $result->toArray();
        $findings = [];

        if ($result->classification->value === 'malicious') {
            $findings[] = new ScanFindingData(
                ruleKey: 'virustotal_malicious_url',
                title: 'VirusTotal reports malicious URL detections',
                description: 'VirusTotal reported malicious engine detections for the authorized hostname URL.',
                severity: SecurityFindingSeverity::High,
                evidence: $evidence,
                recommendedAction: 'Investigate the URL and hosting before further use, remove unauthorized content, and review VirusTotal engine details.',
            );
        } elseif ($result->classification->value === 'suspicious') {
            $findings[] = new ScanFindingData(
                ruleKey: 'virustotal_suspicious_url',
                title: 'VirusTotal reports suspicious URL detections',
                description: 'VirusTotal reported suspicious engine detections for the authorized hostname URL.',
                severity: SecurityFindingSeverity::Medium,
                evidence: $evidence,
                recommendedAction: 'Review the URL, hosting, content, and engine evidence before deciding whether to use or keep it.',
            );
        }

        return new ScanCheckResultData(
            'virustotal_reputation',
            $findings === [] ? ScanCheckOutcome::Passed : ScanCheckOutcome::Finding,
            $findings === []
                ? 'VirusTotal returned URL reputation results with no malicious or suspicious detections.'
                : 'VirusTotal found malicious or suspicious URL reputation detections.',
            $evidence,
            $findings,
        );
    }
}
