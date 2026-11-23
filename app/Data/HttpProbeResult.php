<?php

namespace App\Data;

final readonly class HttpProbeResult
{
    /**
     * @param array<string, string> $headers
     * @param array<string, string> $certificateInfo
     * @param list<string> $setCookieHeaders
     */
    public function __construct(
        public bool $successful,
        public ?int $statusCode,
        public array $headers,
        public array $certificateInfo,
        public ?string $errorCode,
        public ?string $peerAddress,
        public string $body = '',
        public array $setCookieHeaders = [],
        public bool $setCookieHeadersTruncated = false,
    ) {}
}
