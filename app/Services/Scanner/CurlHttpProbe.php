<?php

namespace App\Services\Scanner;

use App\Data\HttpProbeResult;
use App\Services\Scanner\Exceptions\InvalidHostname;
use App\Services\Scanner\Exceptions\PublicTargetRejected;
use RuntimeException;

class CurlHttpProbe implements HttpProbe, WebsitePageProbe
{
    private const MAX_HEADER_BYTES = 16384;

    private const MAX_DOCUMENT_BYTES = 262144;

    private const MAX_SET_COOKIE_HEADERS = 20;

    private const ALLOWED_HEADERS = [
        'content-security-policy',
        'content-type',
        'location',
        'referrer-policy',
        'strict-transport-security',
        'x-content-type-options',
    ];

    public function __construct(
        private readonly HostnamePolicy $hostnamePolicy,
        private readonly PublicAddressPolicy $addressPolicy,
    ) {}

    public function request(string $scheme, string $hostname, string $address): HttpProbeResult
    {
        return $this->sendRequest('HEAD', $scheme, $hostname, $address, 0);
    }

    public function fetchDocument(string $hostname, string $address): HttpProbeResult
    {
        return $this->sendRequest('GET', 'https', $hostname, $address, self::MAX_DOCUMENT_BYTES);
    }

    private function sendRequest(string $method, string $scheme, string $hostname, string $address, int $maxBodyBytes): HttpProbeResult
    {
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('Unsupported scanner protocol.');
        }

        if ($this->hostnamePolicy->normalize($hostname) !== $hostname) {
            throw new InvalidHostname('The scanner hostname is not normalized.');
        }

        if (! $this->addressPolicy->isPublic($address)) {
            throw new PublicTargetRejected('The scanner address is not public.');
        }

        if (! extension_loaded('curl')) {
            throw new RuntimeException('The cURL extension is required for scanning.');
        }

        $port = $scheme === 'https' ? 443 : 80;
        $resolveAddress = str_contains($address, ':') ? '['.$address.']' : $address;
        $headers = [];
        $setCookieHeaders = [];
        $headerBytes = 0;
        $headersTooLarge = false;
        $bodyTooLarge = false;
        $unsupportedContentType = false;
        $setCookieHeadersTruncated = false;
        $body = '';
        $handle = curl_init();

        if ($handle === false) {
            throw new RuntimeException('Unable to initialize the scanner transport.');
        }

        $options = [
            CURLOPT_URL => $scheme.'://'.$hostname.'/',
            CURLOPT_NOBODY => $method === 'HEAD',
            CURLOPT_HTTPGET => $method === 'GET',
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_CONNECTTIMEOUT_MS => 3000,
            CURLOPT_TIMEOUT_MS => 8000,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.1',
                'Cache-Control: no-cache',
                'Connection: close',
            ],
            CURLOPT_ACCEPT_ENCODING => 'identity',
            CURLOPT_USERAGENT => 'ZENTRIX-ASTRA-Defensive-Scanner/1.0',
            CURLOPT_PROXY => '',
            CURLOPT_RESOLVE => [$hostname.':'.$port.':'.$resolveAddress],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CERTINFO => true,
            CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$headers, &$setCookieHeaders, &$headerBytes, &$headersTooLarge, &$setCookieHeadersTruncated): int {
                $headerBytes += strlen($line);

                if ($headerBytes > self::MAX_HEADER_BYTES) {
                    $headersTooLarge = true;

                    return 0;
                }

                if (preg_match('/^HTTP\/\S+\s+\d{3}/i', $line) === 1) {
                    $headers = [];
                    $setCookieHeaders = [];

                    return strlen($line);
                }

                $separator = strpos($line, ':');

                if ($separator === false) {
                    return strlen($line);
                }

                $name = strtolower(trim(substr($line, 0, $separator)));

                if (in_array($name, self::ALLOWED_HEADERS, true)) {
                    $headers[$name] = substr(trim(substr($line, $separator + 1)), 0, 2048);
                } elseif ($name === 'set-cookie') {
                    $value = trim(substr($line, $separator + 1));

                    if (count($setCookieHeaders) >= self::MAX_SET_COOKIE_HEADERS || strlen($value) > 4096) {
                        $setCookieHeadersTruncated = true;
                    } else {
                        $setCookieHeaders[] = $value;
                    }
                }

                return strlen($line);
            },
        ];

        if ($method === 'GET') {
            $options[CURLOPT_WRITEFUNCTION] = function ($curl, string $chunk) use (&$body, &$headers, &$bodyTooLarge, &$unsupportedContentType, $maxBodyBytes): int {
                $contentType = strtolower(trim(explode(';', $headers['content-type'] ?? '', 2)[0]));

                if (! in_array($contentType, ['text/html', 'application/xhtml+xml'], true)) {
                    $unsupportedContentType = true;

                    return 0;
                }

                if (strlen($body) + strlen($chunk) > $maxBodyBytes) {
                    $bodyTooLarge = true;

                    return 0;
                }

                $body .= $chunk;

                return strlen($chunk);
            };
        }

        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $options[CURLOPT_PROTOCOLS_STR] = 'http,https';
        } else {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }

        if (! curl_setopt_array($handle, $options)) {
            curl_close($handle);

            throw new RuntimeException('Unable to configure the scanner transport.');
        }

        $response = curl_exec($handle);
        $info = curl_getinfo($handle);
        $errorNumber = curl_errno($handle);
        $certificateInfo = $scheme === 'https'
            ? $this->certificateInfo(curl_getinfo($handle, CURLINFO_CERTINFO))
            : [];
        curl_close($handle);

        $peerAddress = is_string($info['primary_ip'] ?? null) ? $info['primary_ip'] : null;

        if ($peerAddress !== null && ! $this->sameAddress($address, $peerAddress)) {
            return new HttpProbeResult(false, null, [], [], 'peer_address_mismatch', $peerAddress);
        }

        if ($headersTooLarge) {
            return new HttpProbeResult(false, null, [], [], 'response_headers_too_large', $peerAddress);
        }

        $statusCode = is_int($info['http_code'] ?? null) && $info['http_code'] > 0
            ? $info['http_code']
            : null;

        if ($bodyTooLarge || $unsupportedContentType) {
            return new HttpProbeResult(
                false,
                $statusCode,
                $headers,
                $certificateInfo,
                $bodyTooLarge ? 'response_body_too_large' : 'unsupported_content_type',
                $peerAddress,
                '',
                $setCookieHeaders,
                $setCookieHeadersTruncated,
            );
        }

        if ($response === false) {
            return new HttpProbeResult(
                false,
                $statusCode,
                [],
                [],
                $this->errorCode($errorNumber),
                $peerAddress,
            );
        }

        if ($peerAddress === null) {
            return new HttpProbeResult(false, null, [], [], 'peer_address_mismatch', $peerAddress);
        }

        return new HttpProbeResult(
            successful: $statusCode !== null && $statusCode > 0,
            statusCode: $statusCode,
            headers: $headers,
            certificateInfo: $certificateInfo,
            errorCode: $statusCode === null || $statusCode === 0 ? 'empty_http_response' : null,
            peerAddress: $peerAddress,
            body: $body,
            setCookieHeaders: $setCookieHeaders,
            setCookieHeadersTruncated: $setCookieHeadersTruncated,
        );
    }

    /**
     * @param mixed $certificates
     * @return array<string, string>
     */
    private function certificateInfo(mixed $certificates): array
    {
        if (! is_array($certificates) || ! is_array($certificates[0] ?? null)) {
            return [];
        }

        $allowedFields = ['subject', 'issuer', 'start date', 'expire date', 'signature type', 'public key type'];
        $info = [];

        foreach ($allowedFields as $field) {
            $value = $certificates[0][$field] ?? null;

            if (is_string($value)) {
                $info[$field] = substr(trim($value), 0, 255);
            }
        }

        return $info;
    }

    private function errorCode(int $errorNumber): string
    {
        if ($errorNumber === CURLE_OPERATION_TIMEDOUT) {
            return 'timeout';
        }

        if (in_array($errorNumber, [CURLE_PEER_FAILED_VERIFICATION, CURLE_SSL_CERTPROBLEM, CURLE_SSL_CACERT_BADFILE], true)) {
            return 'tls_validation_failed';
        }

        return 'connection_failed';
    }

    private function sameAddress(string $left, string $right): bool
    {
        $packedLeft = inet_pton($left);
        $packedRight = inet_pton($right);

        return $packedLeft !== false && $packedLeft === $packedRight;
    }
}
