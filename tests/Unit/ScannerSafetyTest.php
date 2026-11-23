<?php

namespace Tests\Unit;

use App\Data\EndpointScanData;
use App\Data\HttpProbeResult;
use App\Enums\ScanCheckOutcome;
use App\Services\Scanner\Checks\HttpsRedirectCheck;
use App\Services\Scanner\Checks\SecurityHeadersCheck;
use App\Services\Scanner\CurlHttpProbe;
use App\Services\Scanner\HostnamePolicy;
use App\Services\Scanner\PublicAddressPolicy;
use App\Services\Scanner\Exceptions\InvalidHostname;
use App\Services\Scanner\Exceptions\PublicTargetRejected;
use PHPUnit\Framework\TestCase;

class ScannerSafetyTest extends TestCase
{
    public function test_hostname_policy_rejects_urls_ip_literals_and_too_long_dns_challenge_names(): void
    {
        $policy = new HostnamePolicy;

        foreach ([
            'https://example.com',
            '127.0.0.1',
            'single-label',
            str_repeat('a', 63).'.'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 49),
        ] as $hostname) {
            try {
                $policy->normalize($hostname);
                $this->fail('Expected hostname to be rejected: '.$hostname);
            } catch (InvalidHostname) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame('example.com', $policy->normalize(' Example.COM. '));
    }

    public function test_public_address_policy_rejects_special_and_private_network_ranges(): void
    {
        $policy = new PublicAddressPolicy;

        foreach ([
            '10.10.0.1',
            '100.64.1.1',
            '127.0.0.1',
            '169.254.169.254',
            '192.0.2.1',
            '198.18.0.1',
            '224.0.0.1',
            '2001:db8::1',
            '2002::1',
            'fc00::1',
            'fe80::1',
        ] as $address) {
            $this->assertFalse($policy->isPublic($address), $address.' should be blocked');
        }

        $this->assertTrue($policy->isPublic('8.8.8.8'));
        $this->assertTrue($policy->isPublic('2606:4700:4700::1111'));
    }

    public function test_curl_probe_refuses_non_public_ip_before_network_io(): void
    {
        $probe = new CurlHttpProbe(new HostnamePolicy, new PublicAddressPolicy);

        $this->expectException(PublicTargetRejected::class);
        $probe->request('https', 'example.com', '127.0.0.1');
    }

    public function test_redirect_check_never_accepts_off_scope_redirect_destinations(): void
    {
        $check = new HttpsRedirectCheck;
        $http = new HttpProbeResult(true, 302, ['location' => 'http://127.0.0.1/admin'], [], null, '8.8.8.8');
        $endpoints = new EndpointScanData(
            $http,
            new HttpProbeResult(true, 200, [], [], null, '8.8.8.8'),
            [],
        );

        $result = $check->run('example.com', $endpoints);

        $this->assertSame(ScanCheckOutcome::Blocked, $result->outcome);
        $this->assertFalse($result->evidence['same_host_https']);
    }

    public function test_security_header_check_reports_only_observed_missing_headers(): void
    {
        $check = new SecurityHeadersCheck;
        $endpoints = new EndpointScanData(
            new HttpProbeResult(false, null, [], [], 'timeout', null),
            new HttpProbeResult(true, 200, ['x-content-type-options' => 'nosniff'], [], null, '8.8.8.8'),
            [],
        );

        $result = $check->run($endpoints);

        $this->assertSame(ScanCheckOutcome::Finding, $result->outcome);
        $this->assertContains('Content-Security-Policy', $result->evidence['missing']);
        $this->assertNotContains('X-Content-Type-Options', $result->evidence['missing']);
        $this->assertCount(3, $result->findings);
    }
}
