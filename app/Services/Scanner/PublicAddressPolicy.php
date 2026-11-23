<?php

namespace App\Services\Scanner;

class PublicAddressPolicy
{
    private const BLOCKED_IPV4_RANGES = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.88.99.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
    ];

    private const BLOCKED_IPV6_RANGES = [
        '2001::/23',
        '2001:db8::/32',
        '2002::/16',
        '3fff::/20',
    ];

    public function isPublic(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        $packedAddress = inet_pton($address);

        if ($packedAddress === false) {
            return false;
        }

        if (strlen($packedAddress) === 4) {
            foreach (self::BLOCKED_IPV4_RANGES as $range) {
                if ($this->isWithinRange($packedAddress, $range)) {
                    return false;
                }
            }

            return true;
        }

        if (! $this->isWithinRange($packedAddress, '2000::/3')) {
            return false;
        }

        foreach (self::BLOCKED_IPV6_RANGES as $range) {
            if ($this->isWithinRange($packedAddress, $range)) {
                return false;
            }
        }

        return true;
    }

    private function isWithinRange(string $packedAddress, string $range): bool
    {
        [$network, $prefix] = explode('/', $range);
        $packedNetwork = inet_pton($network);

        if ($packedNetwork === false || strlen($packedNetwork) !== strlen($packedAddress)) {
            return false;
        }

        $remainingBits = (int) $prefix;

        for ($index = 0, $length = strlen($packedAddress); $index < $length; $index++) {
            if ($remainingBits >= 8) {
                if (ord($packedAddress[$index]) !== ord($packedNetwork[$index])) {
                    return false;
                }

                $remainingBits -= 8;

                continue;
            }

            if ($remainingBits > 0) {
                $mask = (0xff << (8 - $remainingBits)) & 0xff;

                return (ord($packedAddress[$index]) & $mask) === (ord($packedNetwork[$index]) & $mask);
            }

            return true;
        }

        return true;
    }
}
