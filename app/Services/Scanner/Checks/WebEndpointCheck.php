<?php

namespace App\Services\Scanner\Checks;

use App\Data\EndpointScanData;
use App\Data\HttpProbeResult;
use App\Data\ScanCheckResultData;
use App\Enums\ScanCheckOutcome;
use App\Services\Scanner\HttpProbe;

class WebEndpointCheck
{
    public function __construct(private readonly HttpProbe $httpProbe) {}

    public function run(string $hostname, string $address): EndpointScanData
    {
        $http = $this->httpProbe->request('http', $hostname, $address);
        $https = $this->httpProbe->request('https', $hostname, $address);

        return new EndpointScanData(
            http: $http,
            https: $https,
            checkResults: [
                $this->httpResult($http),
                $this->httpsResult($https),
            ],
        );
    }

    private function httpResult(HttpProbeResult $result): ScanCheckResultData
    {
        if (! $result->successful) {
            return new ScanCheckResultData(
                'http_response',
                ScanCheckOutcome::Error,
                'The passive HTTP response check did not complete.',
                ['failure_code' => $result->errorCode ?? 'connection_failed'],
            );
        }

        return new ScanCheckResultData(
            'http_response',
            ScanCheckOutcome::Passed,
            'The HTTP endpoint returned a response.',
            [
                'status_code' => $result->statusCode,
                'peer_address' => $result->peerAddress,
            ],
        );
    }

    private function httpsResult(HttpProbeResult $result): ScanCheckResultData
    {
        if (! $result->successful) {
            return new ScanCheckResultData(
                'https_tls',
                ScanCheckOutcome::Error,
                'The HTTPS/TLS check did not complete with certificate verification enabled.',
                ['failure_code' => $result->errorCode ?? 'connection_failed'],
            );
        }

        return new ScanCheckResultData(
            'https_tls',
            ScanCheckOutcome::Passed,
            'The HTTPS endpoint returned a response with TLS certificate verification enabled.',
            [
                'status_code' => $result->statusCode,
                'peer_address' => $result->peerAddress,
                'certificate' => $result->certificateInfo,
            ],
        );
    }
}
