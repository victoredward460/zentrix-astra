<?php

namespace App\Services\Scanner\Checks;

use App\Data\EndpointScanData;
use App\Data\ScanCheckResultData;
use App\Data\ScanFindingData;
use App\Enums\ScanCheckOutcome;
use App\Enums\SecurityFindingSeverity;

class HttpsRedirectCheck
{
    public function run(string $hostname, EndpointScanData $endpoints): ScanCheckResultData
    {
        $http = $endpoints->http;

        if (! $http->successful) {
            return new ScanCheckResultData(
                'https_redirect',
                ScanCheckOutcome::Inconclusive,
                'The HTTPS redirect behavior could not be verified because the HTTP endpoint did not respond.',
                ['failure_code' => $http->errorCode ?? 'connection_failed'],
            );
        }

        if ($http->statusCode >= 300 && $http->statusCode < 400) {
            if ($this->redirectsToVerifiedHttpsHost($http->headers['location'] ?? null, $hostname)) {
                return new ScanCheckResultData(
                    'https_redirect',
                    ScanCheckOutcome::Passed,
                    'HTTP redirects to HTTPS on the verified hostname.',
                    ['status_code' => $http->statusCode, 'same_host_https' => true],
                );
            }

            return new ScanCheckResultData(
                'https_redirect',
                ScanCheckOutcome::Blocked,
                'The redirect destination was not followed because it was outside the verified HTTPS hostname scope.',
                ['status_code' => $http->statusCode, 'same_host_https' => false],
            );
        }

        $finding = new ScanFindingData(
            ruleKey: 'https_redirect_missing',
            title: 'HTTP response did not redirect to HTTPS',
            description: 'The passive HTTP response did not redirect to HTTPS on the verified hostname. This is an observed configuration gap, not proof of an exploitable vulnerability.',
            severity: SecurityFindingSeverity::Informational,
            evidence: ['http_status' => $http->statusCode],
            recommendedAction: 'Configure the authorized hostname to redirect HTTP traffic to the same hostname over HTTPS.',
        );

        return new ScanCheckResultData(
            'https_redirect',
            ScanCheckOutcome::Finding,
            'The HTTP endpoint did not redirect to HTTPS on the verified hostname.',
            ['status_code' => $http->statusCode, 'same_host_https' => false],
            [$finding],
        );
    }

    private function redirectsToVerifiedHttpsHost(?string $location, string $hostname): bool
    {
        if (! is_string($location) || $location === '') {
            return false;
        }

        $parts = parse_url($location);

        if (! is_array($parts)) {
            return false;
        }

        return strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && strtolower(rtrim((string) ($parts['host'] ?? ''), '.')) === $hostname
            && ($parts['port'] ?? 443) === 443
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }
}
