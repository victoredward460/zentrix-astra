<?php

namespace App\Data;

final readonly class EndpointScanData
{
    /**
     * @param list<ScanCheckResultData> $checkResults
     */
    public function __construct(
        public HttpProbeResult $http,
        public HttpProbeResult $https,
        public array $checkResults,
    ) {}

    public function hasReachableEndpoint(): bool
    {
        return $this->http->successful || $this->https->successful;
    }
}
