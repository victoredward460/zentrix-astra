<?php

namespace App\Services\SafeNavigation;

use App\Services\Scanner\PublicAddressPolicy;
use InvalidArgumentException;

class UrlNormalizer
{
    public function __construct(private readonly PublicAddressPolicy $addressPolicy) {}

    public function normalize(string $input): string
    {
        $url = trim($input);

        if (
            $url === ''
            || strlen($url) > 2048
            || preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1
        ) {
            throw new InvalidArgumentException('Enter a valid HTTP or HTTPS URL without whitespace or control characters.');
        }

        $parts = parse_url($url);

        if (
            ! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new InvalidArgumentException('Only HTTP or HTTPS URLs without embedded credentials are accepted.');
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower(rtrim(trim($parts['host'], '[]'), '.'));
        $isIpAddress = filter_var($host, FILTER_VALIDATE_IP) !== false;

        if ($isIpAddress) {
            if (! $this->addressPolicy->isPublic($host)) {
                throw new InvalidArgumentException('Private, reserved, and local IP destinations are not accepted.');
            }
        } elseif (
            preg_match('/[^\x21-\x7e]/', $host) === 1
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            || ! str_contains($host, '.')
            || preg_match('/\A[0-9.]+\z/', $host) === 1
            || $this->isLocalHostname($host)
        ) {
            throw new InvalidArgumentException('Local, single-label, or invalid hostnames are not accepted.');
        }

        $port = $parts['port'] ?? null;

        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new InvalidArgumentException('The URL port is invalid.');
        }

        $authorityHost = str_contains($host, ':') ? '['.$host.']' : $host;
        $defaultPort = ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
        $path = $parts['path'] ?? '/';
        $normalized = $scheme.'://'.$authorityHost;

        if ($port !== null && ! $defaultPort) {
            $normalized .= ':'.$port;
        }

        $normalized .= $path === '' ? '/' : $path;

        if (isset($parts['query'])) {
            $normalized .= '?'.$parts['query'];
        }

        if (strlen($normalized) > 2048) {
            throw new InvalidArgumentException('The normalized URL is too long.');
        }

        return $normalized;
    }

    private function isLocalHostname(string $host): bool
    {
        foreach (['localhost', 'local', 'internal', 'lan', 'test', 'invalid', 'example', 'home.arpa'] as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }
}
