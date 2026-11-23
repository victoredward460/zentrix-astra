<?php

namespace App\Services\Scanner;

use App\Services\Scanner\Exceptions\PublicTargetRejected;

class PublicTargetResolver
{
    public function __construct(
        private readonly DnsResolver $dnsResolver,
        private readonly PublicAddressPolicy $addressPolicy,
    ) {}

    /**
     * @return list<string>
     */
    public function resolve(string $hostname): array
    {
        $records = $this->dnsResolver->records($hostname, DNS_A | DNS_AAAA);
        $addresses = [];

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        $addresses = array_values(array_unique($addresses));

        if ($addresses === []) {
            throw new PublicTargetRejected('The hostname does not resolve to a public address.');
        }

        foreach ($addresses as $address) {
            if (! $this->addressPolicy->isPublic($address)) {
                throw new PublicTargetRejected('The hostname resolves to a restricted network address.');
            }
        }

        return $addresses;
    }
}
