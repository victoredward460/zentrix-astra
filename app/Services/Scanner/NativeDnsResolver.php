<?php

namespace App\Services\Scanner;

use App\Services\Scanner\Exceptions\DnsLookupFailed;

class NativeDnsResolver implements DnsResolver
{
    public function records(string $hostname, int $type): array
    {
        $records = @dns_get_record($hostname, $type);

        if ($records === false) {
            throw new DnsLookupFailed('DNS lookup failed.');
        }

        return $records;
    }
}
