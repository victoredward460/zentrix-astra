<?php

namespace Tests\Feature;

use App\Enums\FileCheckStatus;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\FileCheck\FileCheckService;
use App\Services\AI\OpenRouterEndpointDiscovery;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Request;
use Tests\TestCase;

class FileCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->mock(OpenRouterEndpointDiscovery::class, function ($mock): void {
            $mock->shouldReceive('eligibleProviders')->andReturn(['nvidia']);
        });
        config([
            'ai.openrouter.require_zdr' => true,
            'ai.openrouter.data_collection' => 'deny',
        ]);
        Storage::fake('file-checks');
        Storage::fake('public');
    }

    public function test_file_check_requires_authentication(): void
    {
        $this->get(route('file-check.index'))
            ->assertRedirect(route('login'));
    }

    public function test_upload_persists_private_metadata_and_sha256_without_a_verdict(): void
    {
        $user = User::factory()->create();
        $contents = 'known benign test fixture';

        $response = $this->actingAs($user)->post(route('file-check.store'), [
            'file' => UploadedFile::fake()->createWithContent('example.txt', $contents),
        ]);

        $fileCheck = $user->fileChecks()->sole();
        $response->assertRedirect(route('file-check.show', $fileCheck));

        $this->assertSame(FileCheckStatus::Completed, $fileCheck->status);
        $this->assertSame(hash('sha256', $contents), $fileCheck->sha256);
        $this->assertSame(strlen($contents), $fileCheck->size_bytes);
        $this->assertSame('example.txt', $fileCheck->original_name);
        $this->assertSame('completed', $fileCheck->fresh()->status->value);
        Storage::disk('file-checks')->assertExists($fileCheck->storage_key);
        Storage::disk('public')->assertMissing($fileCheck->storage_key);

        $this->get(route('file-check.show', $fileCheck))
            ->assertOk()
            ->assertSee($fileCheck->sha256)
            ->assertSee('No malware analysis was performed')
            ->assertSee('not available for download')
            ->assertDontSee($fileCheck->storage_key);
    }

    public function test_explicit_hash_check_saves_virustotal_results_without_uploading_file_content(): void
    {
        config(['safe_navigation.providers.virustotal.credential' => 'vt-test-secret']);
        Http::fake([
            'https://www.virustotal.com/api/v3/files/*' => Http::response([
                'data' => [
                    'attributes' => [
                        'last_analysis_stats' => [
                            'malicious' => 2,
                            'suspicious' => 1,
                            'harmless' => 40,
                            'undetected' => 15,
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $contents = 'private test fixture';
        $this->actingAs($user)->post(route('file-check.store'), [
            'file' => UploadedFile::fake()->createWithContent('fixture.bin', $contents),
        ]);
        $fileCheck = $user->fileChecks()->sole();

        $this->post(route('file-check.virustotal', $fileCheck))
            ->assertRedirect()
            ->assertSessionHas('status');

        $fileCheck->refresh();
        $this->assertSame('malicious', $fileCheck->vt_status);
        $this->assertSame(2, $fileCheck->vt_malicious);
        $this->assertNotNull($fileCheck->vt_last_checked_at);

        Http::assertSent(fn ($request): bool =>
            str_contains($request->url(), '/api/v3/files/'.hash('sha256', $contents))
            && $request->method() === 'GET'
            && $request->body() === ''
        );
        Storage::disk('file-checks')->assertExists($fileCheck->storage_key);
    }

    public function test_message_analysis_reports_local_signals_without_saving_or_sending_message_text(): void
    {
        $user = User::factory()->create();
        $message = 'Your account is locked. Enter your password and send it now: https://bit.ly/example';

        $this->actingAs($user)
            ->post(route('file-check.message-analysis'), ['message' => $message])
            ->assertRedirect(route('file-check.index'))
            ->assertSessionHas('message_analysis', function (array $analysis): bool {
                return $analysis['level'] === 'High concern'
                    && $analysis['url_count'] === 1
                    && count($analysis['signals']) >= 2;
            });

        $this->get(route('file-check.index'))
            ->assertOk()
            ->assertSee('High concern')
            ->assertSee('authentication secret')
            ->assertSee('URL-shortening service')
            ->assertSee('local checks work without provider access')
            ->assertDontSee('ai_processing_confirmed')
            ->assertDontSee($message);

        $this->assertDatabaseCount('file_checks', 0);
        Http::assertNothingSent();
    }

    public function test_consented_message_analysis_sends_only_the_message_for_an_ai_explanation(): void
    {
        config([
            'services.openrouter.api_key' => 'test-key',
            'services.openrouter.model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
        ]);
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
                'choices' => [['message' => ['content' => 'Do not click the link; verify through a trusted channel.']]],
            ]),
        ]);

        $user = User::factory()->create();
        $this->grantAiProviderConsent($user);
        $message = 'Private message with a suspicious link: https://example.test/verify';

        $this->actingAs($user)
            ->post(route('file-check.message-analysis'), [
                'message' => $message,
                'include_ai_explanation' => '1',
            ])
            ->assertRedirect(route('file-check.index'))
            ->assertSessionHas('message_analysis.ai_explanation.content', 'Do not click the link; verify through a trusted channel.')
            ->assertSessionHas('message_analysis.ai_explanation.model', 'nvidia/nemotron-3-ultra-550b-a55b:free');

        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && str_contains($request->data()['messages'][1]['content'], $message)
            && $request->data()['model'] === 'nvidia/nemotron-3-ultra-550b-a55b:free'
            && $request->data()['provider']['only'] === ['nvidia']
            && $request->data()['provider']['allow_fallbacks'] === false
            && $request->data()['provider']['data_collection'] === 'deny'
            && $request->data()['provider']['zdr'] === true
            && $request->data()['provider']['max_price'] === ['prompt' => 0, 'completion' => 0]
        );
        $this->assertDatabaseCount('file_checks', 0);
        $this->assertDatabaseCount('agent_messages', 0);
    }

    public function test_file_ai_explanation_requires_saved_consent_without_a_per_request_checkbox(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $storageKey = $user->id.'/synthetic.bin';
        Storage::disk('file-checks')->put($storageKey, 'synthetic');
        $fileCheck = $user->fileChecks()->create([
            'storage_key' => $storageKey,
            'original_name' => 'synthetic.bin',
            'size_bytes' => 9,
            'mime_type' => 'application/octet-stream',
            'sha256' => hash('sha256', 'synthetic'),
            'status' => FileCheckStatus::Completed,
            'processed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('file-check.show', $fileCheck))
            ->assertOk()
            ->assertSee('Enable AI once in your profile')
            ->assertDontSee('ai_processing_confirmed');

        $this->post(route('file-check.ai-explanation', $fileCheck))
            ->assertRedirect(route('file-check.show', $fileCheck))
            ->assertSessionHasErrors('ai_provider_consent');
        Http::assertNothingSent();
    }

    public function test_ai_file_explanation_uses_saved_model_without_repeated_consent_and_sends_only_metadata(): void
    {
        config([
            'services.openrouter.api_key' => 'test-key',
            'services.openrouter.model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
        ]);
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'model' => 'nvidia/nemotron-3.5-super-vl:free',
                'choices' => [['message' => ['content' => 'These counts are limited reputation evidence, not a file scan.']]],
            ]),
        ]);

        $user = User::factory()->create();
        $this->grantAiProviderConsent($user, 'nvidia/nemotron-3.5-super-vl:free');
        $fileContents = 'private uploaded bytes';
        $storageKey = $user->id.'/private-filename-secret.bin';
        Storage::disk('file-checks')->put($storageKey, $fileContents);
        $sha256 = hash('sha256', $fileContents);
        $fileCheck = $user->fileChecks()->create([
            'storage_key' => $storageKey,
            'original_name' => 'private-filename-secret.bin',
            'size_bytes' => strlen($fileContents),
            'mime_type' => 'application/octet-stream',
            'sha256' => $sha256,
            'status' => FileCheckStatus::Completed,
            'vt_status' => 'suspicious',
            'vt_malicious' => 1,
            'vt_suspicious' => 2,
            'vt_harmless' => 10,
            'vt_undetected' => 3,
            'processed_at' => now(),
        ]);

        $this->actingAs($user)
            ->from(route('file-check.show', $fileCheck))
            ->post(route('file-check.ai-explanation', $fileCheck))
            ->assertRedirect(route('file-check.show', $fileCheck))
            ->assertSessionHas('ai_file_explanation.content', 'These counts are limited reputation evidence, not a file scan.');

        Http::assertSent(function (Request $request) use ($fileContents, $fileCheck, $sha256, $storageKey): bool {
            $payload = $request->data();
            $prompt = $payload['messages'][1]['content'];

            return $payload['model'] === 'nvidia/nemotron-3.5-super-vl:free'
                && $payload['reasoning'] === ['effort' => 'none']
                && str_contains($prompt, 'application/octet-stream')
                && str_contains($prompt, '"malicious":1')
                && ! str_contains($prompt, $fileContents)
                && ! str_contains($prompt, $fileCheck->original_name)
                && ! str_contains($prompt, $sha256)
                && ! str_contains($prompt, $storageKey);
        });
        Storage::disk('file-checks')->assertExists($storageKey);
    }

    public function test_message_analysis_does_not_save_invalid_message_input_in_the_session(): void
    {
        $user = User::factory()->create();
        $message = str_repeat('private message content ', 250);

        $this->actingAs($user)
            ->from(route('file-check.index'))
            ->post(route('file-check.message-analysis'), ['message' => $message])
            ->assertRedirect(route('file-check.index'))
            ->assertSessionHasErrors('message');

        $this->assertNull(session()->getOldInput('message'));
        $this->assertDatabaseCount('file_checks', 0);
    }

    public function test_users_cannot_read_or_delete_another_users_file_records(): void
    {
        $owner = User::factory()->create();
        $anotherUser = User::factory()->create();
        $storageKey = $owner->id.'/private-fixture.bin';
        Storage::disk('file-checks')->put($storageKey, 'private');
        $fileCheck = $owner->fileChecks()->create([
            'storage_key' => $storageKey,
            'original_name' => 'private.bin',
            'size_bytes' => 7,
            'mime_type' => 'application/octet-stream',
            'sha256' => hash('sha256', 'private'),
            'status' => FileCheckStatus::Completed,
            'processed_at' => now(),
        ]);

        $this->actingAs($anotherUser)
            ->get(route('file-check.show', $fileCheck))
            ->assertNotFound();

        $this->delete(route('file-check.destroy', $fileCheck))
            ->assertNotFound();

        Http::fake();
        $this->post(route('file-check.ai-explanation', $fileCheck))->assertNotFound();

        $this->assertDatabaseHas('file_checks', ['id' => $fileCheck->id]);
        Storage::disk('file-checks')->assertExists($storageKey);
        Http::assertNothingSent();
    }

    public function test_upload_is_required_and_limited_to_ten_mib(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('file-check.index'))
            ->post(route('file-check.store'))
            ->assertRedirect(route('file-check.index'))
            ->assertSessionHasErrors('file');

        $this->post(route('file-check.store'), [
            'file' => UploadedFile::fake()->create('oversized.bin', FileCheckService::MAX_SIZE_KILOBYTES + 1),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('file_checks', 0);
        Storage::disk('file-checks')->assertDirectoryEmpty((string) $user->id);
    }

    public function test_failed_storage_attempt_is_recorded_and_reported(): void
    {
        $user = User::factory()->create();
        $disk = \Mockery::mock(FilesystemAdapter::class);
        Storage::shouldReceive('disk')->with('file-checks')->andReturn($disk);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        $disk->shouldReceive('exists')->once()->andReturn(false);

        $this->actingAs($user)
            ->from(route('file-check.index'))
            ->post(route('file-check.store'), [
                'file' => UploadedFile::fake()->createWithContent('write-failure.txt', 'test'),
            ])
            ->assertRedirect(route('file-check.index'))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseHas('file_checks', [
            'user_id' => $user->id,
            'original_name' => 'write-failure.txt',
            'status' => FileCheckStatus::Failed->value,
            'failure_code' => 'processing_failed',
            'sha256' => null,
        ]);
    }

    public function test_owner_can_delete_file_and_record(): void
    {
        $user = User::factory()->create();
        $storageKey = $user->id.'/delete-fixture.bin';
        Storage::disk('file-checks')->put($storageKey, 'delete me');
        $fileCheck = $user->fileChecks()->create([
            'storage_key' => $storageKey,
            'original_name' => 'delete.bin',
            'size_bytes' => 9,
            'mime_type' => 'application/octet-stream',
            'sha256' => hash('sha256', 'delete me'),
            'status' => FileCheckStatus::Completed,
            'processed_at' => now(),
        ]);

        $this->actingAs($user)
            ->delete(route('file-check.destroy', $fileCheck))
            ->assertRedirect(route('file-check.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('file_checks', ['id' => $fileCheck->id]);
        Storage::disk('file-checks')->assertMissing($storageKey);
    }

    public function test_retention_command_removes_expired_private_objects_and_records(): void
    {
        $user = User::factory()->create();
        $storageKey = $user->id.'/expired-fixture.bin';
        Storage::disk('file-checks')->put($storageKey, 'expired');
        $fileCheck = $user->fileChecks()->create([
            'storage_key' => $storageKey,
            'original_name' => 'expired.bin',
            'size_bytes' => 7,
            'mime_type' => 'application/octet-stream',
            'sha256' => hash('sha256', 'expired'),
            'status' => FileCheckStatus::Completed,
            'processed_at' => now()->subDays(FileCheckService::RETENTION_DAYS + 1),
        ]);
        $fileCheck->forceFill([
            'created_at' => now()->subDays(FileCheckService::RETENTION_DAYS + 1),
            'updated_at' => now()->subDays(FileCheckService::RETENTION_DAYS + 1),
        ])->save();

        $this->artisan('file-checks:prune')
            ->expectsOutput('Deleted 1 expired file check(s).')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('file_checks', ['id' => $fileCheck->id]);
        Storage::disk('file-checks')->assertMissing($storageKey);
    }

    public function test_dashboard_counts_and_lists_only_the_current_users_file_records(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $user->fileChecks()->create([
            'storage_key' => $user->id.'/owned.bin',
            'original_name' => 'owned-report.txt',
            'size_bytes' => 12,
            'mime_type' => 'text/plain',
            'sha256' => hash('sha256', 'owned report'),
            'status' => FileCheckStatus::Completed,
            'processed_at' => now(),
        ]);
        $anotherUser->fileChecks()->create([
            'storage_key' => $anotherUser->id.'/private.bin',
            'original_name' => 'another-account-secret.txt',
            'size_bytes' => 6,
            'mime_type' => 'text/plain',
            'sha256' => hash('sha256', 'secret'),
            'status' => FileCheckStatus::Completed,
            'processed_at' => now(),
        ]);

        $dashboard = app(DashboardService::class)->forUser($user);

        $this->assertSame(1, $dashboard->metrics?->fileChecks);
        $this->assertSame('File Check completed', $dashboard->activityTimeline->first()->title);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('File checks')
            ->assertSee('File Check completed')
            ->assertSee('owned-report.txt')
            ->assertDontSee('another-account-secret.txt');
    }

    private function grantAiProviderConsent(User $user, ?string $model = null): void
    {
        $user->forceFill([
            'ai_model' => $model ?? $user->aiModel(),
            'ai_data_sharing_consent_at' => now(),
        ])->save();
    }
}
