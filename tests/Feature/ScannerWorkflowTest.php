<?php

namespace Tests\Feature;

use App\Data\HttpProbeResult;
use App\Enums\AssetVerificationStatus;
use App\Enums\FindingVerificationState;
use App\Enums\ScanStatus;
use App\Enums\SecurityFindingSeverity;
use App\Enums\SecurityFindingState;
use App\Jobs\ExecuteSecurityScan;
use App\Models\ScanRun;
use App\Models\SecurityAsset;
use App\Models\User;
use App\Services\Scanner\DnsResolver;
use App\Services\Scanner\DomainOwnershipService;
use App\Services\Scanner\Exceptions\OwnershipVerificationFailed;
use App\Services\Scanner\HttpProbe;
use App\Services\Scanner\SecurityScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScannerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['safe_navigation.providers.virustotal.credential' => null]);
    }

    public function test_guest_cannot_access_scanner_and_authenticated_user_sees_scanner(): void
    {
        $this->get(route('scanner.assets.index'))->assertRedirect(route('login'));

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('scanner.assets.index'))
            ->assertOk()
            ->assertSee('Passive checks only')
            ->assertSee('Start with a hostname');
    }

    public function test_asset_registration_requires_explicit_authorization_and_creates_dns_challenge(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('scanner.assets.store'), ['hostname' => 'example.com'])
            ->assertSessionHasErrors('authorization_confirmed');
        $this->assertDatabaseCount('security_assets', 0);

        $response = $this->post(route('scanner.assets.store'), [
            'hostname' => ' Example.COM. ',
            'authorization_confirmed' => '1',
        ])->assertRedirect(route('scanner.assets.index'))
            ->assertSessionHas('ownership_challenge');

        $this->assertDatabaseHas('security_assets', [
            'user_id' => $user->id,
            'hostname' => 'example.com',
            'verification_status' => AssetVerificationStatus::Pending->value,
        ]);
        $this->assertStringStartsWith('zentrix-astra-verification=', $response->getSession()->get('ownership_challenge')['record_value']);
    }

    public function test_dns_txt_proof_verifies_and_consumes_the_ownership_challenge(): void
    {
        $user = User::factory()->create();
        $asset = $this->createAsset($user);
        $issuer = new DomainOwnershipService(new class implements DnsResolver
        {
            public function records(string $hostname, int $type): array
            {
                return [];
            }
        });
        $challenge = $issuer->issue($asset);
        $resolver = new class($challenge->recordValue()) implements DnsResolver
        {
            public function __construct(private readonly string $recordValue) {}

            public function records(string $hostname, int $type): array
            {
                return [['entries' => [$this->recordValue]]];
            }
        };

        (new DomainOwnershipService($resolver))->verify($asset);

        $this->assertSame(AssetVerificationStatus::Verified, $asset->fresh()->verification_status);
        $this->assertNull($asset->fresh()->verification_token_hash);
        $this->assertNull($asset->fresh()->verification_expires_at);
        $this->assertNotNull($asset->fresh()->verified_at);
    }

    public function test_expired_dns_challenge_cannot_verify(): void
    {
        $user = User::factory()->create();
        $asset = $this->createAsset($user);
        $resolver = new class implements DnsResolver
        {
            public function records(string $hostname, int $type): array
            {
                return [['entries' => ['zentrix-astra-verification=irrelevant']]];
            }
        };
        $service = new DomainOwnershipService($resolver);
        $service->issue($asset);
        $asset->forceFill(['verification_expires_at' => now()->subSecond()])->save();

        $this->expectException(OwnershipVerificationFailed::class);
        $service->verify($asset);
    }

    public function test_unverified_asset_cannot_be_scanned_and_user_cannot_access_another_users_records(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $asset = $this->createAsset($owner);

        $this->actingAs($owner)
            ->post(route('scanner.assets.scans.store', $asset))
            ->assertForbidden();

        $run = $asset->scanRuns()->create([
            'user_id' => $owner->id,
            'status' => ScanStatus::Queued,
            'requested_at' => now(),
        ]);

        $this->actingAs($otherUser)
            ->get(route('scanner.runs.show', $run))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('scanner.runs.show', $run))
            ->assertOk()
            ->assertSee('Scan result');
    }

    public function test_verified_asset_scan_is_queued_and_worker_persists_results_and_findings(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $asset = $this->createAsset($user, AssetVerificationStatus::Verified);

        $this->actingAs($user)
            ->post(route('scanner.assets.scans.store', $asset))
            ->assertRedirect();

        $scanRun = ScanRun::query()->sole();
        $this->assertSame(ScanStatus::Queued, $scanRun->status);
        Queue::assertPushed(ExecuteSecurityScan::class);

        $resolver = new class implements DnsResolver
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
        $probe = new class implements HttpProbe
        {
            public function request(string $scheme, string $hostname, string $address): HttpProbeResult
            {
                $headers = $scheme === 'https' ? [
                    'content-security-policy' => "default-src 'self'",
                    'referrer-policy' => 'strict-origin-when-cross-origin',
                    'strict-transport-security' => 'max-age=31536000',
                    'x-content-type-options' => 'nosniff',
                ] : ['location' => 'https://'.$hostname.'/'];

                return new HttpProbeResult(true, $scheme === 'https' ? 200 : 301, $headers, [], null, $address);
            }
        };

        $this->app->instance(DnsResolver::class, $resolver);
        $this->app->instance(HttpProbe::class, $probe);
        app(SecurityScanService::class)->execute($scanRun->id);

        $scanRun->refresh();
        $this->assertSame(ScanStatus::Completed, $scanRun->status);
        $this->assertSame(6, $scanRun->checkResults()->count());
        $this->assertSame(0, $scanRun->findings()->count());
        $this->assertSame(
            ['8.8.8.8'],
            $scanRun->checkResults()->where('check_key', 'dns_configuration')->sole()->evidence['public_addresses'],
        );
        $this->assertSame(
            'inconclusive',
            $scanRun->checkResults()->where('check_key', 'virustotal_reputation')->sole()->outcome->value,
        );
        $this->assertNotNull($scanRun->started_at);
        $this->assertNotNull($scanRun->finished_at);
    }

    public function test_worker_marks_private_dns_resolution_blocked_without_probing(): void
    {
        $user = User::factory()->create();
        $asset = $this->createAsset($user, AssetVerificationStatus::Verified);
        $scanRun = $asset->scanRuns()->create([
            'user_id' => $user->id,
            'status' => ScanStatus::Queued,
            'requested_at' => now(),
        ]);

        $resolver = new class implements DnsResolver
        {
            public function records(string $hostname, int $type): array
            {
                return [['ip' => '169.254.169.254']];
            }
        };
        $probe = new class implements HttpProbe
        {
            public function request(string $scheme, string $hostname, string $address): HttpProbeResult
            {
                throw new \RuntimeException('Probe must not be invoked for a blocked target.');
            }
        };

        $this->app->instance(DnsResolver::class, $resolver);
        $this->app->instance(HttpProbe::class, $probe);
        app(SecurityScanService::class)->execute($scanRun->id);

        $this->assertSame(ScanStatus::Failed, $scanRun->fresh()->status);
        $this->assertSame('target_blocked', $scanRun->fresh()->failure_code);
        $this->assertSame('blocked', $scanRun->checkResults()->sole()->outcome->value);
    }

    public function test_worker_records_timeout_errors_and_fails_unreachable_scans(): void
    {
        $user = User::factory()->create();
        $asset = $this->createAsset($user, AssetVerificationStatus::Verified);
        $scanRun = $asset->scanRuns()->create([
            'user_id' => $user->id,
            'status' => ScanStatus::Queued,
            'requested_at' => now(),
        ]);
        $resolver = new class implements DnsResolver
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
        $probe = new class implements HttpProbe
        {
            public function request(string $scheme, string $hostname, string $address): HttpProbeResult
            {
                return new HttpProbeResult(false, null, [], [], 'timeout', $address);
            }
        };

        $this->app->instance(DnsResolver::class, $resolver);
        $this->app->instance(HttpProbe::class, $probe);
        app(SecurityScanService::class)->execute($scanRun->id);

        $this->assertSame(ScanStatus::Failed, $scanRun->fresh()->status);
        $this->assertSame('target_unreachable', $scanRun->fresh()->failure_code);
        $this->assertSame(6, $scanRun->checkResults()->count());
        $this->assertTrue(
            $scanRun->checkResults()->where('evidence->failure_code', 'timeout')->exists()
        );
    }

    public function test_dashboard_uses_real_owner_scoped_scan_and_finding_records(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $asset = $this->createAsset($user, AssetVerificationStatus::Verified);
        $otherAsset = $this->createAsset($otherUser, AssetVerificationStatus::Verified);
        $scanRun = $asset->scanRuns()->create([
            'user_id' => $user->id,
            'status' => ScanStatus::Completed,
            'requested_at' => now()->subMinutes(4),
            'started_at' => now()->subMinutes(3),
            'finished_at' => now()->subMinutes(2),
        ]);
        $otherAsset->scanRuns()->create([
            'user_id' => $otherUser->id,
            'status' => ScanStatus::Completed,
            'requested_at' => now()->subMinutes(4),
            'started_at' => now()->subMinutes(3),
            'finished_at' => now()->subMinutes(2),
        ]);
        $scanRun->findings()->create([
            'user_id' => $user->id,
            'rule_key' => 'example-observation',
            'title' => 'Owner-only observed configuration gap',
            'description' => 'A test observation tied to a persisted scan response.',
            'severity' => SecurityFindingSeverity::Informational,
            'verification_state' => FindingVerificationState::Unverified,
            'state' => SecurityFindingState::Open,
            'evidence' => ['observed' => true],
            'detected_at' => now()->subMinute(),
        ]);
        $otherRun = $otherAsset->scanRuns()->first();
        $otherRun->findings()->create([
            'user_id' => $otherUser->id,
            'rule_key' => 'private-observation',
            'title' => 'Other account private observation',
            'description' => 'This record must remain private.',
            'severity' => SecurityFindingSeverity::High,
            'verification_state' => FindingVerificationState::Unverified,
            'state' => SecurityFindingState::Open,
            'evidence' => ['observed' => true],
            'detected_at' => now(),
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee($asset->hostname)
            ->assertSee('Owner-only observed configuration gap')
            ->assertDontSee($otherAsset->hostname)
            ->assertDontSee('Other account private observation')
            ->assertSee('1 records');
    }

    private function createAsset(User $user, AssetVerificationStatus $status = AssetVerificationStatus::Pending): SecurityAsset
    {
        $asset = $user->securityAssets()->create([
            'hostname' => fake()->unique()->domainName(),
            'authorization_attested_at' => now(),
        ]);

        if ($status === AssetVerificationStatus::Verified) {
            $asset->forceFill([
                'verification_status' => $status,
                'verified_at' => now(),
            ])->save();
        }

        return $asset->fresh();
    }
}
