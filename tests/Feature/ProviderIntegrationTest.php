<?php

namespace Tests\Feature;

use App\Enums\AgentProviderFailure;
use App\Exceptions\AgentProviderException;
use App\Enums\UrlThreatClassification;
use App\Services\SafeNavigation\VirusTotalProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openrouter.api_key' => null,
            'services.openrouter.model' => null,
            'ai.openrouter.require_zdr' => true,
            'ai.openrouter.data_collection' => 'deny',
            'safe_navigation.providers.virustotal.credential' => null,
        ]);
    }

    public function test_openrouter_accepts_only_exact_free_model_variants(): void
    {
        config([
            'services.openrouter.api_key' => 'sk-or-v1-test',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.openrouter.model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
        ]);
        $this->assertTrue(app(VirusTotalProvider::class)->key() === 'virustotal');
        $this->assertTrue(app(\App\Services\AI\OpenRouterChatProvider::class)->isConfigured());

        config(['services.openrouter.model' => 'example/paid-model']);
        $this->assertFalse(app(\App\Services\AI\OpenRouterChatProvider::class)->isConfigured());

        config(['services.openrouter.model' => 'openrouter/free']);
        $this->assertFalse(app(\App\Services\AI\OpenRouterChatProvider::class)->isConfigured());
    }

    public function test_openrouter_rejects_non_official_base_urls(): void
    {
        config([
            'services.openrouter.api_key' => 'openrouter-test-secret',
            'services.openrouter.base_url' => 'https://attacker.example/api/v1',
            'services.openrouter.model' => 'openrouter/free',
        ]);

        $this->assertFalse(app(\App\Services\AI\OpenRouterChatProvider::class)->isConfigured());
    }

    public function test_virustotal_uses_a_url_identifier_and_reports_only_aggregated_counts(): void
    {
        config(['safe_navigation.providers.virustotal.credential' => 'vt-test-secret']);
        Http::fake([
            'https://www.virustotal.com/api/v3/urls/*' => Http::response([
                'data' => [
                    'attributes' => [
                        'last_analysis_stats' => [
                            'malicious' => 2,
                            'suspicious' => 1,
                            'harmless' => 50,
                        ],
                    ],
                ],
            ]),
        ]);

        $url = 'https://example.com/a?b=c';
        $result = app(VirusTotalProvider::class)->assess($url);
        $urlId = rtrim(strtr(base64_encode($url), '+/', '-_'), '=');

        $this->assertSame(UrlThreatClassification::Malicious, $result->classification);
        $this->assertSame('VirusTotal reported 2 malicious and 1 suspicious detections.', $result->evidence);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), rawurlencode($urlId))
            && $request->hasHeader('x-apikey', 'vt-test-secret'));
    }

    public function test_virustotal_hash_lookup_never_uploads_file_content(): void
    {
        config(['safe_navigation.providers.virustotal.credential' => 'vt-test-secret']);
        Http::fake([
            'https://www.virustotal.com/api/v3/files/*' => Http::response([
                'data' => [
                    'attributes' => [
                        'last_analysis_stats' => [
                            'malicious' => 3,
                            'suspicious' => 1,
                            'harmless' => 50,
                            'undetected' => 4,
                        ],
                    ],
                ],
            ]),
        ]);

        $sha256 = hash('sha256', 'private fixture');
        $result = app(VirusTotalProvider::class)->lookupFileHash($sha256);

        $this->assertSame('found', $result['status']);
        $this->assertSame('malicious', $result['classification']);
        $this->assertSame(3, $result['malicious']);
        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://www.virustotal.com/api/v3/files/'.$sha256
            && $request->method() === 'GET'
            && $request->hasHeader('x-apikey', 'vt-test-secret')
            && $request->body() === ''
        );
    }

    public function test_virustotal_hash_lookup_reports_not_found_without_fabricating_a_verdict(): void
    {
        config(['safe_navigation.providers.virustotal.credential' => 'vt-test-secret']);
        Http::fake([
            'https://www.virustotal.com/api/v3/files/*' => Http::response([], 404),
        ]);

        $result = app(VirusTotalProvider::class)->lookupFileHash(hash('sha256', 'unknown fixture'));

        $this->assertSame('not_found', $result['status']);
        $this->assertSame('unknown', $result['classification']);
    }

    public function test_integration_check_skips_unconfigured_providers_and_hides_credentials(): void
    {
        Http::fake();

        Artisan::call('astra:check-integrations');

        $output = Artisan::output();

        $this->assertStringContainsString('OpenRouter', $output);
        $this->assertStringContainsString('Not tested', $output);
        $this->assertStringNotContainsString('test-secret', $output);
        Http::assertNothingSent();
    }

    public function test_live_integration_check_queries_each_configured_adapter_without_printing_secrets(): void
    {
        config([
            'services.openrouter.api_key' => 'openrouter-test-secret',
            'services.openrouter.model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
            'safe_navigation.providers.virustotal.credential' => 'vt-test-secret',
        ]);
        Http::fake([
            'https://openrouter.ai/api/v1/key' => Http::response(['data' => ['label' => 'test-key']]),
            'https://openrouter.ai/api/v1/models/nvidia/nemotron-3-ultra-550b-a55b:free/endpoints' => Http::response([
                'data' => ['endpoints' => [[
                    'provider_name' => 'Nvidia',
                    'tag' => 'nvidia',
                    'pricing' => ['prompt' => '0', 'completion' => '0'],
                ]]],
            ]),
            'https://openrouter.ai/api/v1/endpoints/zdr' => Http::response([
                'data' => [[
                    'model_id' => 'nvidia/nemotron-3-ultra-550b-a55b',
                    'provider_name' => 'Nvidia',
                    'tag' => 'nvidia',
                    'safety_retention' => false,
                ]],
            ]),
            'https://www.virustotal.com/api/v3/urls/*' => Http::response([
                'data' => ['attributes' => ['last_analysis_stats' => ['malicious' => 0, 'suspicious' => 0]]],
            ]),
        ]);

        Artisan::call('astra:check-integrations');

        $output = Artisan::output();

        $this->assertStringContainsString('Key authenticated; free strict-ZDR NVIDIA endpoint metadata found; inference not tested', $output);
        $this->assertStringContainsString('Provider responded', $output);
        $this->assertStringNotContainsString('test-secret', $output);
        Http::assertSentCount(4);
    }

    public function test_integration_check_rejects_an_invalid_openrouter_key_before_checking_models(): void
    {
        config([
            'services.openrouter.api_key' => 'openrouter-test-secret',
            'services.openrouter.model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
            'safe_navigation.providers.virustotal.credential' => null,
        ]);
        Http::fake([
            'https://openrouter.ai/api/v1/key' => Http::response(['error' => 'Invalid API key'], 401),
            'https://openrouter.ai/api/v1/models/nvidia/nemotron-3-ultra-550b-a55b:free/endpoints' => Http::response([
                'data' => ['endpoints' => [[
                    'provider_name' => 'NVIDIA',
                    'tag' => 'nvidia',
                    'pricing' => ['prompt' => '0', 'completion' => '0'],
                ]]],
            ]),
        ]);

        Artisan::call('astra:check-integrations');

        $output = Artisan::output();

        $this->assertStringContainsString('Request rejected (HTTP 401)', $output);
        $this->assertStringNotContainsString('authenticated', $output);
        $this->assertStringNotContainsString('openrouter-test-secret', $output);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openrouter.ai/api/v1/key');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/models/'));
    }

    public function test_integration_check_reports_when_the_selected_free_model_is_not_available(): void
    {
        config([
            'services.openrouter.api_key' => 'openrouter-test-secret',
            'services.openrouter.model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
            'safe_navigation.providers.virustotal.credential' => null,
        ]);
        Http::fake([
            'https://openrouter.ai/api/v1/key' => Http::response(['data' => ['label' => 'test-key']]),
            'https://openrouter.ai/api/v1/models/nvidia/nemotron-3-ultra-550b-a55b:free/endpoints' => Http::response([], 404),
        ]);

        $exitCode = Artisan::call('astra:check-integrations');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('no available zero-cost endpoint', $output);
        $this->assertStringNotContainsString('openrouter-test-secret', $output);
        Http::assertSentCount(2);
    }

    public function test_openrouter_preflight_rejects_a_free_endpoint_without_a_matching_zdr_endpoint(): void
    {
        config([
            'services.openrouter.api_key' => 'openrouter-test-secret',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.openrouter.model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
        ]);
        Http::fake([
            'https://openrouter.ai/api/v1/models/nvidia/nemotron-3-ultra-550b-a55b:free/endpoints' => Http::response([
                'data' => ['endpoints' => [[
                    'provider_name' => 'NVIDIA',
                    'tag' => 'nvidia',
                    'pricing' => ['prompt' => '0', 'completion' => '0'],
                ]]],
            ]),
            'https://openrouter.ai/api/v1/endpoints/zdr' => Http::response(['data' => []]),
        ]);

        try {
            app(\App\Services\AI\OpenRouterChatProvider::class)->complete([
                ['role' => 'user', 'content' => 'Use a synthetic test prompt.'],
            ]);
            $this->fail('A response should not be generated without a policy-compatible endpoint.');
        } catch (AgentProviderException $exception) {
            $this->assertSame(AgentProviderFailure::PrivacyPolicyUnavailable, $exception->failure);
            $this->assertStringContainsString('Check whether model training is allowed', $exception->failure->userMessage());
        }

        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/chat/completions'));
    }

    public function test_openrouter_reports_account_data_policy_rejection_as_a_privacy_failure(): void
    {
        config([
            'services.openrouter.api_key' => 'openrouter-test-secret',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.openrouter.model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
            'ai.openrouter.require_zdr' => false,
        ]);
        $this->mock(\App\Services\AI\OpenRouterEndpointDiscovery::class, function ($mock): void {
            $mock->shouldReceive('eligibleProviders')->once()->andReturn(['nvidia']);
        });
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'error' => [
                    'message' => 'No endpoints found matching your data policy (Free model training).',
                ],
            ], 404),
        ]);

        try {
            app(\App\Services\AI\OpenRouterChatProvider::class)->complete([
                ['role' => 'user', 'content' => 'Harmless synthetic privacy-policy test.'],
            ]);
            $this->fail('A provider excluded by account data policy must not generate a response.');
        } catch (AgentProviderException $exception) {
            $this->assertSame(AgentProviderFailure::PrivacyPolicyUnavailable, $exception->failure);
        }
    }

    public function test_endpoint_discovery_intersects_exact_free_model_provider_and_zdr_metadata(): void
    {
        config(['services.openrouter.base_url' => 'https://openrouter.ai/api/v1']);
        Cache::flush();
        Http::fake([
            'https://openrouter.ai/api/v1/models/nvidia/nemotron-3-ultra-550b-a55b:free/endpoints' => Http::response([
                'data' => ['endpoints' => [
                    [
                        'provider_name' => 'Nvidia',
                        'tag' => 'nvidia',
                        'pricing' => ['prompt' => '0', 'completion' => '0'],
                    ],
                    [
                        'provider_name' => 'Paid endpoint',
                        'tag' => 'paid',
                        'pricing' => ['prompt' => '0.1', 'completion' => '0.1'],
                    ],
                ]],
            ]),
            'https://openrouter.ai/api/v1/endpoints/zdr' => Http::response([
                'data' => [
                    [
                        'model_id' => 'nvidia/nemotron-3-ultra-550b-a55b',
                        'provider_name' => 'Nvidia',
                        'tag' => 'nvidia',
                        'safety_retention' => false,
                    ],
                    [
                        'model_id' => 'nvidia/nemotron-3-ultra-550b-a55b',
                        'provider_name' => 'Paid endpoint',
                        'tag' => 'paid',
                        'safety_retention' => false,
                    ],
                ],
            ]),
        ]);

        $providers = app(\App\Services\AI\OpenRouterEndpointDiscovery::class)
            ->eligibleProviders('nvidia/nemotron-3-ultra-550b-a55b:free');

        $this->assertSame(['nvidia'], $providers);
    }

    public function test_endpoint_discovery_rejects_retained_data_endpoints(): void
    {
        config(['services.openrouter.base_url' => 'https://openrouter.ai/api/v1']);
        Cache::flush();
        Http::fake([
            'https://openrouter.ai/api/v1/models/nvidia/nemotron-3-ultra-550b-a55b:free/endpoints' => Http::response([
                'data' => ['endpoints' => [[
                    'provider_name' => 'Nvidia',
                    'tag' => 'nvidia',
                    'pricing' => ['prompt' => '0', 'completion' => '0'],
                ]]],
            ]),
            'https://openrouter.ai/api/v1/endpoints/zdr' => Http::response([
                'data' => [[
                    'model_id' => 'nvidia/nemotron-3-ultra-550b-a55b',
                    'provider_name' => 'Nvidia',
                    'tag' => 'nvidia',
                    'safety_retention' => true,
                ]],
            ]),
        ]);

        try {
            app(\App\Services\AI\OpenRouterEndpointDiscovery::class)
                ->eligibleProviders('nvidia/nemotron-3-ultra-550b-a55b:free');
            $this->fail('A retained-data endpoint must not qualify for strict ZDR.');
        } catch (AgentProviderException $exception) {
            $this->assertSame(AgentProviderFailure::PrivacyPolicyUnavailable, $exception->failure);
        }

    }

    public function test_chat_request_keeps_the_selected_model_and_enforces_zero_price_privacy_routing(): void
    {
        config([
            'services.openrouter.api_key' => 'openrouter-test-secret',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.openrouter.model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
        ]);
        $this->mock(\App\Services\AI\OpenRouterEndpointDiscovery::class, function ($mock): void {
            $mock->shouldReceive('eligibleProviders')->once()->with('nvidia/nemotron-3-ultra-550b-a55b:free')->andReturn(['nvidia']);
        });
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
                'choices' => [['message' => ['content' => 'Synthetic response.']]],
            ]),
        ]);

        $reply = app(\App\Services\AI\OpenRouterChatProvider::class)->complete([
            ['role' => 'user', 'content' => 'Return a short synthetic response.'],
        ]);

        $this->assertSame('Synthetic response.', $reply->content);
        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && $request->data()['model'] === 'nvidia/nemotron-3-ultra-550b-a55b:free'
            && $request->data()['max_tokens'] === 700
            && ! array_key_exists('reasoning', $request->data())
            && $request->data()['provider'] === [
                'only' => ['nvidia'],
                'allow_fallbacks' => false,
                'data_collection' => 'deny',
                'max_price' => ['prompt' => 0, 'completion' => 0],
                'zdr' => true,
            ]
        );
    }

    public function test_default_discovery_selects_free_nvidia_without_requiring_zdr_metadata(): void
    {
        config([
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
        ]);
        config()->offsetUnset('ai.openrouter.require_zdr');
        Cache::flush();
        Http::fake([
            'https://openrouter.ai/api/v1/models/nvidia/nemotron-3-ultra-550b-a55b:free/endpoints' => Http::response([
                'data' => ['endpoints' => [
                    [
                        'provider_name' => 'Nvidia',
                        'tag' => 'nvidia',
                        'pricing' => ['prompt' => '0', 'completion' => '0'],
                    ],
                    [
                        'provider_name' => 'Another Provider',
                        'tag' => 'other',
                        'pricing' => ['prompt' => '0', 'completion' => '0'],
                    ],
                ]],
            ]),
        ]);

        $providers = app(\App\Services\AI\OpenRouterEndpointDiscovery::class)
            ->eligibleProviders('nvidia/nemotron-3-ultra-550b-a55b:free');

        $this->assertSame(['nvidia'], $providers);
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/endpoints/zdr'));
    }

    public function test_default_completion_allows_data_collection_without_relaxing_provider_or_price_limits(): void
    {
        config([
            'services.openrouter.api_key' => 'openrouter-test-secret',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.openrouter.model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
            'ai.openrouter.data_collection' => 'allow',
        ]);
        config()->offsetUnset('ai.openrouter.require_zdr');
        $this->mock(\App\Services\AI\OpenRouterEndpointDiscovery::class, function ($mock): void {
            $mock->shouldReceive('eligibleProviders')->once()->andReturn(['nvidia']);
        });
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
                'choices' => [['message' => ['content' => 'Synthetic response.']]],
            ]),
        ]);

        app(\App\Services\AI\OpenRouterChatProvider::class)->complete([
            ['role' => 'user', 'content' => 'Return a short synthetic response.'],
        ]);

        Http::assertSent(fn (Request $request): bool =>
            $request->data()['provider'] === [
                'only' => ['nvidia'],
                'allow_fallbacks' => false,
                'data_collection' => 'allow',
                'max_price' => ['prompt' => 0, 'completion' => 0],
            ]
            && ! array_key_exists('zdr', $request->data()['provider'])
        );
    }

    public function test_explicit_data_collection_consent_does_not_change_provider_or_price_limits(): void
    {
        config([
            'services.openrouter.api_key' => 'openrouter-test-secret',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.openrouter.model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
            'ai.openrouter.require_zdr' => false,
            'ai.openrouter.data_collection' => 'allow',
        ]);
        $this->mock(\App\Services\AI\OpenRouterEndpointDiscovery::class, function ($mock): void {
            $mock->shouldReceive('eligibleProviders')->once()->andReturn(['nvidia']);
        });
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
                'choices' => [['message' => ['content' => 'Synthetic response.']]],
            ]),
        ]);

        app(\App\Services\AI\OpenRouterChatProvider::class)->complete(
            [['role' => 'user', 'content' => 'Return a short synthetic response.']],
            maxTokens: 2500,
            disableReasoning: true,
        );

        Http::assertSent(fn (Request $request): bool =>
            $request->data()['provider'] === [
                'only' => ['nvidia'],
                'allow_fallbacks' => false,
                'data_collection' => 'allow',
                'max_price' => ['prompt' => 0, 'completion' => 0],
            ]
            && $request->data()['max_tokens'] === 2500
            && $request->data()['reasoning'] === ['effort' => 'none']
        );
    }

    public function test_endpoint_discovery_retries_transient_metadata_errors(): void
    {
        config(['services.openrouter.base_url' => 'https://openrouter.ai/api/v1']);
        Cache::flush();
        Http::fake([
            'https://openrouter.ai/api/v1/models/nvidia/nemotron-3-ultra-550b-a55b:free/endpoints' => Http::sequence()
                ->push([], 502)
                ->push([
                    'data' => ['endpoints' => [[
                        'provider_name' => 'Nvidia',
                        'tag' => 'nvidia',
                        'pricing' => ['prompt' => '0', 'completion' => '0'],
                    ]]],
                ]),
            'https://openrouter.ai/api/v1/endpoints/zdr' => Http::response([
                'data' => [[
                    'model_id' => 'nvidia/nemotron-3-ultra-550b-a55b',
                    'provider_name' => 'Nvidia',
                    'tag' => 'nvidia',
                    'safety_retention' => false,
                ]],
            ]),
        ]);

        $providers = app(\App\Services\AI\OpenRouterEndpointDiscovery::class)
            ->eligibleProviders('nvidia/nemotron-3-ultra-550b-a55b:free');

        $this->assertSame(['nvidia'], $providers);
        Http::assertSentCount(3);
    }

    public function test_rate_limit_retry_after_is_reported_without_repeating_completion_posts(): void
    {
        config([
            'services.openrouter.api_key' => 'openrouter-test-secret',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.openrouter.model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
        ]);
        $this->mock(\App\Services\AI\OpenRouterEndpointDiscovery::class, function ($mock): void {
            $mock->shouldReceive('eligibleProviders')->once()->andReturn(['nvidia']);
        });
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'error' => ['message' => 'Rate limit exceeded.'],
            ], 429, ['Retry-After' => '30']),
        ]);

        try {
            app(\App\Services\AI\OpenRouterChatProvider::class)->complete([
                ['role' => 'user', 'content' => 'Short synthetic test.'],
            ]);
            $this->fail('A rate-limited completion should fail explicitly.');
        } catch (AgentProviderException $exception) {
            $this->assertSame(AgentProviderFailure::RateLimited, $exception->failure);
            $this->assertStringContainsString('Retry in 30 seconds', $exception->userMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_chat_rejects_a_response_from_a_different_model(): void
    {
        config([
            'services.openrouter.api_key' => 'openrouter-test-secret',
            'services.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'services.openrouter.model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
        ]);
        $this->mock(\App\Services\AI\OpenRouterEndpointDiscovery::class, function ($mock): void {
            $mock->shouldReceive('eligibleProviders')->once()->andReturn(['nvidia']);
        });
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'model' => 'another/provider:free',
                'choices' => [['message' => ['content' => 'Must not be accepted.']]],
            ]),
        ]);

        try {
            app(\App\Services\AI\OpenRouterChatProvider::class)->complete([
                ['role' => 'user', 'content' => 'Short synthetic test.'],
            ]);
            $this->fail('A response from a different model must be rejected.');
        } catch (AgentProviderException $exception) {
            $this->assertSame(AgentProviderFailure::ModelUnavailable, $exception->failure);
        }
    }
}
