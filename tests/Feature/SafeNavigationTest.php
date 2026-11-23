<?php

namespace Tests\Feature;

use App\Data\UrlReputationProviderResult;
use App\Enums\UrlThreatClassification;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\SafeNavigation\UrlReputationProvider;
use App\Services\SafeNavigation\UrlReputationProviderException;
use App\Services\SafeNavigation\UrlReputationProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SafeNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config([
            'safe_navigation.providers.virustotal.credential' => '',
        ]);
    }

    public function test_safe_navigation_requires_administrator_access_for_every_route(): void
    {
        $this->get(route('safe-navigation.index'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $admin = $this->createAdministrator();

        $this->actingAs($user)
            ->get(route('safe-navigation.index'))
            ->assertForbidden();

        $this->post(route('safe-navigation.checks.store'), $this->submission('https://example.com'))
            ->assertForbidden();
        $this->assertDatabaseCount('safe_navigation_checks', 0);

        $check = $this->submit($admin, 'https://example.com/admin-only')->assertRedirect();
        $adminCheck = $admin->safeNavigationChecks()->sole();

        $otherAdmin = $this->createAdministrator();
        $this->actingAs($otherAdmin)
            ->get(route('safe-navigation.checks.show', $adminCheck))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('safe-navigation.checks.show', $adminCheck))
            ->assertForbidden();
    }

    public function test_url_is_normalized_and_saved_as_integration_unavailable_without_network_requests(): void
    {
        Http::fake();
        $admin = $this->createAdministrator();

        $this->submit($admin, 'HTTPS://EXAMPLE.COM:443/path?token=redacted#client-fragment')
            ->assertRedirect(route('safe-navigation.checks.show', $admin->safeNavigationChecks()->sole()))
            ->assertSessionHas('status');

        $check = $admin->safeNavigationChecks()->sole();
        $this->assertSame('https://example.com/path?token=redacted', $check->url);
        $this->assertSame(UrlThreatClassification::IntegrationUnavailable, $check->classification);
        $this->assertCount(1, $check->provider_results);
        $this->assertSame(
            ['integration_unavailable'],
            array_values(array_unique(array_column($check->provider_results, 'classification'))),
        );

        $this->get(route('safe-navigation.checks.show', $check))
            ->assertOk()
            ->assertSee('Integration Unavailable')
            ->assertSee('No reputation provider is connected')
            ->assertSee('risk is unknown')
            ->assertSee($check->url)
            ->assertDontSee('href="https://example.com/path', false);

        Http::assertNothingSent();
    }

    public function test_private_local_and_credentialed_urls_are_rejected(): void
    {
        $admin = $this->createAdministrator();

        foreach ([
            'javascript:alert(1)',
            'https://127.0.0.1/admin',
            'http://localhost/',
            'http://service.internal/',
            'https://user:pass@example.com/',
            'https://192.0.2.1/',
            'http://127.1/',
            'http://2130706433/',
            'https://example.com:70000/',
        ] as $url) {
            $this->actingAs($admin)
                ->from(route('safe-navigation.index'))
                ->post(route('safe-navigation.checks.store'), $this->submission($url))
                ->assertRedirect(route('safe-navigation.index'))
                ->assertSessionHasErrors('url');
        }

        $this->assertDatabaseCount('safe_navigation_checks', 0);
    }

    public function test_authorization_and_provider_disclosure_are_required(): void
    {
        $admin = $this->createAdministrator();

        $this->actingAs($admin)
            ->get(route('safe-navigation.index'))
            ->assertOk()
            ->assertSee('every reputation provider that has credentials configured', false);

        $this->actingAs($admin)
            ->from(route('safe-navigation.index'))
            ->post(route('safe-navigation.checks.store'), [
                'url' => 'https://example.com',
                'authorization_confirmed' => '1',
            ])
            ->assertRedirect(route('safe-navigation.index'))
            ->assertSessionHasErrors(['provider_disclosure_confirmed']);

        $this->assertDatabaseCount('safe_navigation_checks', 0);
    }

    public function test_provider_results_are_interpreted_without_claiming_unverified_urls_are_safe(): void
    {
        $provider = new class implements UrlReputationProvider
        {
            public function key(): string
            {
                return 'virustotal';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function assess(string $normalizedUrl): UrlReputationProviderResult
            {
                $classification = match (parse_url($normalizedUrl, PHP_URL_PATH)) {
                    '/known-dangerous' => UrlThreatClassification::Malicious,
                    '/suspicious' => UrlThreatClassification::Suspicious,
                    '/not-listed' => UrlThreatClassification::NoThreatDetected,
                    '/stale-match' => UrlThreatClassification::Malicious,
                    default => UrlThreatClassification::UnableToVerify,
                };

                if (parse_url($normalizedUrl, PHP_URL_PATH) === '/provider-exception') {
                    throw new UrlReputationProviderException;
                }

                return new UrlReputationProviderResult(
                    providerKey: $this->key(),
                    providerName: 'Synthetic reputation provider',
                    classification: $classification,
                    threatTypes: $classification === UrlThreatClassification::Malicious ? ['PHISHING_URL'] : [],
                    evidence: 'Synthetic provider result for classification testing.',
                    cacheExpiresAt: $classification === UrlThreatClassification::Malicious
                        ? (parse_url($normalizedUrl, PHP_URL_PATH) === '/stale-match'
                            ? now()->subMinute()->toImmutable()
                            : now()->addMinutes(60)->toImmutable())
                        : ($classification === UrlThreatClassification::Suspicious
                            ? now()->addMinutes(15)->toImmutable()
                            : null),
                );
            }
        };
        $this->app->instance(UrlReputationProviderRegistry::class, new UrlReputationProviderRegistry([$provider]));
        $admin = $this->createAdministrator();
        $expected = [
            '/known-dangerous' => UrlThreatClassification::Malicious,
            '/suspicious' => UrlThreatClassification::Suspicious,
            '/not-listed' => UrlThreatClassification::NoThreatDetected,
            '/provider-error' => UrlThreatClassification::UnableToVerify,
            '/stale-match' => UrlThreatClassification::UnableToVerify,
            '/provider-exception' => UrlThreatClassification::UnableToVerify,
        ];

        foreach ($expected as $path => $classification) {
            $this->submit($admin, 'https://example.com'.$path)->assertRedirect();
            $this->assertSame($classification, $admin->safeNavigationChecks()->latest('id')->firstOrFail()->classification);
        }

        $maliciousCheck = $admin->safeNavigationChecks()->where('url', 'https://example.com/known-dangerous')->firstOrFail();
        $this->get(route('safe-navigation.checks.show', $maliciousCheck))
            ->assertOk()
            ->assertSee('Do not visit it.')
            ->assertSee('Synthetic reputation provider')
            ->assertSee('PHISHING_URL');

        $noThreatCheck = $admin->safeNavigationChecks()->where('url', 'https://example.com/not-listed')->firstOrFail();
        $this->get(route('safe-navigation.checks.show', $noThreatCheck))
            ->assertOk()
            ->assertSee('No threat was detected by the available sources')
            ->assertSee('not proof that the URL is safe');

        $failedCheck = $admin->safeNavigationChecks()->where('url', 'https://example.com/provider-error')->firstOrFail();
        $this->get(route('safe-navigation.checks.show', $failedCheck))
            ->assertOk()
            ->assertSee('Unable To Verify');

        $staleCheck = $admin->safeNavigationChecks()->where('url', 'https://example.com/stale-match')->firstOrFail();
        $this->get(route('safe-navigation.checks.show', $staleCheck))
            ->assertOk()
            ->assertSee('Unable To Verify')
            ->assertSee('expired')
            ->assertDontSee('PHISHING_URL');

        $exceptionCheck = $admin->safeNavigationChecks()->where('url', 'https://example.com/provider-exception')->firstOrFail();
        $this->assertStringContainsString(
            'no reputation result was available',
            implode(' ', array_column($exceptionCheck->provider_results, 'evidence')),
        );
    }

    public function test_retention_command_removes_old_url_history(): void
    {
        $admin = $this->createAdministrator();
        $check = $admin->safeNavigationChecks()->create([
            'url' => 'https://example.com/old',
            'classification' => UrlThreatClassification::IntegrationUnavailable,
            'provider_results' => [],
            'assessed_at' => now()->subDays(31),
        ]);
        $check->forceFill([
            'created_at' => now()->subDays(31),
            'updated_at' => now()->subDays(31),
        ])->save();

        $this->artisan('safe-navigation:prune')
            ->expectsOutput('Deleted 1 expired Safe Navigation check(s).')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('safe_navigation_checks', ['id' => $check->id]);
    }

    private function submit(User $admin, string $url)
    {
        return $this->actingAs($admin)->post(
            route('safe-navigation.checks.store'),
            $this->submission($url),
        );
    }

    /**
     * @return array{url: string, authorization_confirmed: string, provider_disclosure_confirmed: string}
     */
    private function submission(string $url): array
    {
        return [
            'url' => $url,
            'authorization_confirmed' => '1',
            'provider_disclosure_confirmed' => '1',
        ];
    }

    private function createAdministrator(): User
    {
        $administrator = User::factory()->create();
        $administrator->forceFill(['role' => UserRole::Administrator])->save();

        return $administrator->fresh();
    }
}
