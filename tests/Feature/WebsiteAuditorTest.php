<?php

namespace Tests\Feature;

use App\Data\HttpProbeResult;
use App\Enums\AssetVerificationStatus;
use App\Enums\ScanStatus;
use App\Enums\ScanType;
use App\Enums\SecurityFindingSeverity;
use App\Jobs\ExecuteSecurityScan;
use App\Models\SecurityAsset;
use App\Models\User;
use App\Services\Scanner\DnsResolver;
use App\Services\Scanner\HttpProbe;
use App\Services\Scanner\SecurityScanService;
use App\Services\Scanner\WebsitePageProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebsiteAuditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['safe_navigation.providers.virustotal.credential' => null]);
    }

    public function test_auditor_requires_authentication_and_lists_only_verified_owned_sites(): void
    {
        $this->get(route('website-auditor.index'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $verifiedAsset = $this->createVerifiedAsset($user);
        $pendingAsset = $user->securityAssets()->create([
            'hostname' => fake()->unique()->domainName(),
            'authorization_attested_at' => now(),
        ]);
        $otherUser = User::factory()->create();
        $otherAsset = $this->createVerifiedAsset($otherUser);

        $this->actingAs($user)
            ->get(route('website-auditor.index'))
            ->assertOk()
            ->assertSee($verifiedAsset->hostname)
            ->assertDontSee($pendingAsset->hostname)
            ->assertDontSee($otherAsset->hostname)
            ->assertSee('will not follow redirects or fetch embedded resources');
    }

    public function test_audit_submission_requires_fresh_authorization_and_queues_shared_scan_run(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $asset = $this->createVerifiedAsset($user);

        $this->actingAs($user)
            ->post(route('website-auditor.assets.assess', $asset))
            ->assertSessionHasErrors('authorization_confirmed');
        $this->assertDatabaseCount('scan_runs', 0);

        $response = $this->post(route('website-auditor.assets.assess', $asset), [
            'authorization_confirmed' => '1',
        ])->assertRedirect();

        $scanRun = $user->scanRuns()->sole();
        $this->assertSame(ScanType::WebsiteAudit, $scanRun->scan_type);
        $this->assertSame(ScanStatus::Queued, $scanRun->status);
        $this->assertSame($response->headers->get('Location'), route('website-auditor.runs.show', $scanRun));
        Queue::assertPushed(ExecuteSecurityScan::class);
    }

    public function test_audit_routes_hide_other_users_runs_and_scanner_reports(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $asset = $this->createVerifiedAsset($otherUser);
        $otherRun = $asset->scanRuns()->create([
            'user_id' => $otherUser->id,
            'scan_type' => ScanType::WebsiteAudit,
            'status' => ScanStatus::Queued,
            'requested_at' => now(),
        ]);
        $scannerRun = $user->scanRuns()->create([
            'security_asset_id' => $this->createVerifiedAsset($user)->id,
            'scan_type' => ScanType::SecurityScanner,
            'status' => ScanStatus::Completed,
            'requested_at' => now(),
            'finished_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('website-auditor.assets.assess', $asset), ['authorization_confirmed' => '1'])
            ->assertNotFound();

        $this->get(route('website-auditor.runs.show', $otherRun))->assertNotFound();

        $this->get(route('website-auditor.runs.show', $scannerRun))
            ->assertNotFound();
    }

    public function test_audit_reuses_the_safe_scan_pipeline_and_printable_evidence_report(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $asset = $this->createVerifiedAsset($user);
        $this->actingAs($user)
            ->post(route('website-auditor.assets.assess', $asset), ['authorization_confirmed' => '1'])
            ->assertRedirect();
        $scanRun = $user->scanRuns()->sole();

        $dnsResolver = new class implements DnsResolver
        {
            public function records(string $hostname, int $type): array
            {
                return match ($type) {
                    DNS_A | DNS_AAAA => [['ip' => '8.8.8.8']],
                    DNS_MX => [],
                    default => [],
                };
            }
        };
        $httpProbe = new class implements HttpProbe
        {
            public function request(string $scheme, string $hostname, string $address): HttpProbeResult
            {
                return $scheme === 'http'
                    ? new HttpProbeResult(true, 301, ['location' => 'https://'.$hostname.'/'], [], null, $address)
                    : new HttpProbeResult(
                        true,
                        200,
                        [
                            'content-type' => 'text/html; charset=utf-8',
                            'content-security-policy' => "default-src 'self'",
                            'referrer-policy' => 'strict-origin-when-cross-origin',
                            'strict-transport-security' => 'max-age=31536000',
                            'x-content-type-options' => 'nosniff',
                        ],
                        [],
                        null,
                        $address,
                        setCookieHeaders: ['sid=example-secret; HttpOnly; SameSite=Lax'],
                    );
            }
        };
        $pageProbe = new class implements WebsitePageProbe
        {
            public function fetchDocument(string $hostname, string $address): HttpProbeResult
            {
                return new HttpProbeResult(
                    true,
                    200,
                    ['content-type' => 'text/html; charset=utf-8'],
                    [],
                    null,
                    $address,
                    '<!doctype html><html><head><title>Example Portal</title><meta name="description" content="Portal summary"></head><body><script src="http://cdn.example.test/app.js"></script><img src="http://images.example.test/banner.jpg"></body></html>',
                );
            }
        };

        $this->app->instance(DnsResolver::class, $dnsResolver);
        $this->app->instance(HttpProbe::class, $httpProbe);
        $this->app->instance(WebsitePageProbe::class, $pageProbe);
        app(SecurityScanService::class)->execute($scanRun->id);

        $reportUrl = route('website-auditor.runs.show', $scanRun);
        $response = $this->get($reportUrl)
            ->assertOk()
            ->assertSee('Example Portal')
            ->assertSee('Portal summary')
            ->assertSee('Print / Save PDF')
            ->assertSee('Recommended corrective action')
            ->assertSee('Serve each referenced resource over HTTPS')
            ->assertSee('Moderate')
            ->assertSee('It is not a complete security rating or assurance that the website is secure.')
            ->assertDontSee('example-secret');

        $this->assertSame(ScanStatus::Completed, $scanRun->fresh()->status);
        $this->assertSame(9, $scanRun->checkResults()->count());
        $this->assertFalse($scanRun->checkResults()->where('check_key', 'openphish_reputation')->exists());
        $this->assertTrue($scanRun->checkResults()->where('check_key', 'virustotal_reputation')->exists());
        $this->assertSame(2, $scanRun->findings()->count());
        $this->assertDatabaseHas('security_findings', [
            'scan_run_id' => $scanRun->id,
            'rule_key' => 'website_mixed_content',
            'severity' => SecurityFindingSeverity::Medium->value,
        ]);
        $this->assertNotEmpty($scanRun->findings()->whereNotNull('recommended_action')->get());
        $storedEvidence = DB::table('security_findings')
            ->where('scan_run_id', $scanRun->id)
            ->pluck('evidence')
            ->implode(' ');
        $this->assertStringNotContainsString('example-secret', $storedEvidence);
        $this->assertStringNotContainsString('example-secret', $response->getContent());
    }

    public function test_private_dns_answer_blocks_audit_before_http_or_document_requests(): void
    {
        $user = User::factory()->create();
        $asset = $this->createVerifiedAsset($user);
        $scanRun = $asset->scanRuns()->create([
            'user_id' => $user->id,
            'scan_type' => ScanType::WebsiteAudit,
            'status' => ScanStatus::Queued,
            'requested_at' => now(),
        ]);
        $dnsResolver = new class implements DnsResolver
        {
            public function records(string $hostname, int $type): array
            {
                return [['ip' => '169.254.169.254']];
            }
        };
        $httpProbe = new class implements HttpProbe
        {
            public function request(string $scheme, string $hostname, string $address): HttpProbeResult
            {
                throw new \RuntimeException('Blocked addresses must not be probed.');
            }
        };
        $pageProbe = new class implements WebsitePageProbe
        {
            public function fetchDocument(string $hostname, string $address): HttpProbeResult
            {
                throw new \RuntimeException('Blocked addresses must not be fetched.');
            }
        };

        $this->app->instance(DnsResolver::class, $dnsResolver);
        $this->app->instance(HttpProbe::class, $httpProbe);
        $this->app->instance(WebsitePageProbe::class, $pageProbe);
        app(SecurityScanService::class)->execute($scanRun->id);

        $this->assertSame(ScanStatus::Failed, $scanRun->fresh()->status);
        $this->assertSame('target_blocked', $scanRun->fresh()->failure_code);
        $this->assertSame(1, $scanRun->checkResults()->count());
    }

    private function createVerifiedAsset(User $user): SecurityAsset
    {
        $asset = $user->securityAssets()->create([
            'hostname' => fake()->unique()->domainName(),
            'authorization_attested_at' => now(),
        ]);
        $asset->forceFill([
            'verification_status' => AssetVerificationStatus::Verified,
            'verified_at' => now(),
        ])->save();

        return $asset->fresh();
    }
}
