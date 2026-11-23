<?php

namespace App\Services\Scanner;

interface DnsResolver
{
    /**
     * @return list<array<string, mixed>>
     */
    public function records(string $hostname, int $type): array;
}
