<?php

namespace App\Services\Scanner\Checks;

use App\Data\ScanCheckResultData;
use App\Enums\ScanCheckOutcome;
use App\Services\Scanner\DnsResolver;
use App\Services\Scanner\Exceptions\DnsLookupFailed;

class DnsConfigurationCheck
{
    public function __construct(private readonly DnsResolver $dnsResolver) {}

    /**
     * @param list<string> $addresses
     */
    public function run(string $hostname, array $addresses): ScanCheckResultData
    {
        try {
            $mailRecords = $this->dnsResolver->records($hostname, DNS_MX);
        } catch (DnsLookupFailed) {
            return new ScanCheckResultData(
                checkKey: 'dns_configuration',
                outcome: ScanCheckOutcome::Error,
                summary: 'Basic DNS configuration details could not be retrieved.',
                evidence: ['failure_code' => 'dns_lookup_failed'],
            );
        }

        return new ScanCheckResultData(
            checkKey: 'dns_configuration',
            outcome: ScanCheckOutcome::Passed,
            summary: 'Public address records were resolved and basic mail-record presence was checked.',
            evidence: [
                'public_addresses' => array_values($addresses),
                'public_address_count' => count($addresses),
                'mail_record_count' => count($mailRecords),
                'mail_records_present' => $mailRecords !== [],
            ],
        );
    }
}
