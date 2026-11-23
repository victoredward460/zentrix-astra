<?php

namespace App\Services\Scanner;

use App\Services\Scanner\Exceptions\InvalidHostname;

class HostnamePolicy
{
    private const RESERVED_SUFFIXES = [
        'arpa',
        'example',
        'internal',
        'invalid',
        'local',
        'localhost',
        'onion',
        'test',
    ];

    public function normalize(string $hostname): string
    {
        $hostname = strtolower(rtrim(trim($hostname), '.'));

        if (
            $hostname === ''
            || strlen($hostname) > 253
            || strlen('_zentrix-astra-verification.'.$hostname) > 253
            || filter_var($hostname, FILTER_VALIDATE_IP) !== false
        ) {
            throw new InvalidHostname('Enter a public DNS hostname, not a URL or IP address.');
        }

        $labels = explode('.', $hostname);

        if (count($labels) < 2) {
            throw new InvalidHostname('Enter a fully qualified public DNS hostname.');
        }

        foreach ($labels as $label) {
            if (
                strlen($label) > 63
                || preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', $label) !== 1
            ) {
                throw new InvalidHostname('The hostname format is invalid.');
            }
        }

        $topLevelLabel = end($labels);

        if (! is_string($topLevelLabel) || preg_match('/^[a-z]{2,63}$/D', $topLevelLabel) !== 1) {
            throw new InvalidHostname('Enter a hostname with a public DNS suffix.');
        }

        foreach (self::RESERVED_SUFFIXES as $suffix) {
            if ($hostname === $suffix || str_ends_with($hostname, '.'.$suffix)) {
                throw new InvalidHostname('Reserved and local hostnames cannot be scanned.');
            }
        }

        return $hostname;
    }
}
