<?php

namespace App\Services\Scanner\Checks;

use App\Data\EndpointScanData;
use App\Data\ScanCheckResultData;
use App\Data\ScanFindingData;
use App\Enums\ScanCheckOutcome;
use App\Enums\SecurityFindingSeverity;

class SecurityHeadersCheck
{
    private const REQUIRED_HEADERS = [
        'content-security-policy' => 'Content-Security-Policy',
        'referrer-policy' => 'Referrer-Policy',
        'strict-transport-security' => 'Strict-Transport-Security',
        'x-content-type-options' => 'X-Content-Type-Options',
    ];

    public function run(EndpointScanData $endpoints): ScanCheckResultData
    {
        if (! $endpoints->https->successful) {
            return new ScanCheckResultData(
                'security_headers',
                ScanCheckOutcome::Inconclusive,
                'Security headers were not evaluated because no verified HTTPS response was available.',
                ['failure_code' => $endpoints->https->errorCode ?? 'https_unavailable'],
            );
        }

        $missing = [];
        $findings = [];

        foreach (self::REQUIRED_HEADERS as $key => $header) {
            if (array_key_exists($key, $endpoints->https->headers)) {
                continue;
            }

            $missing[] = $header;
            $findings[] = new ScanFindingData(
                ruleKey: 'missing_header_'.str_replace('-', '_', $key),
                title: $header.' header not observed',
                description: 'The passive HTTPS response did not contain '.$header.'. This is a directly observed configuration gap, not proof of an exploitable vulnerability.',
                severity: SecurityFindingSeverity::Informational,
                evidence: ['header' => $header, 'present' => false],
                recommendedAction: match ($key) {
                    'content-security-policy' => 'Define a Content-Security-Policy appropriate for the site, starting with report-only mode if compatibility is uncertain.',
                    'referrer-policy' => 'Set a Referrer-Policy that limits cross-origin referrer detail, such as strict-origin-when-cross-origin.',
                    'strict-transport-security' => 'After confirming HTTPS works across the hostname, configure an appropriate Strict-Transport-Security policy.',
                    default => 'Set X-Content-Type-Options: nosniff on the HTTPS response.',
                },
            );
        }

        if ($missing === []) {
            return new ScanCheckResultData(
                'security_headers',
                ScanCheckOutcome::Passed,
                'All checked security headers were present in the HTTPS response.',
                ['present' => array_values(self::REQUIRED_HEADERS)],
            );
        }

        return new ScanCheckResultData(
            'security_headers',
            ScanCheckOutcome::Finding,
            'One or more checked security headers were not observed.',
            ['present' => array_values(array_diff(self::REQUIRED_HEADERS, $missing)), 'missing' => $missing],
            $findings,
        );
    }
}
