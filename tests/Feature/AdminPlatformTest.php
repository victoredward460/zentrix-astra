<?php

namespace Tests\Feature;

use App\Enums\AssetVerificationStatus;
use App\Enums\FindingVerificationState;
use App\Enums\ScanStatus;
use App\Enums\ScanType;
use App\Enums\SecurityFindingSeverity;
use App\Enums\SecurityFindingState;
use App\Enums\UserRole;
use App\Models\AcademyCategory;
use App\Models\AcademyCourse;
use App\Models\AcademyLesson;
use App\Models\AcademyLessonProgress;
use App\Models\AcademyQuiz;
use App\Models\AcademyQuizAttempt;
use App\Models\PlatformAuditEvent;
use App\Models\User;
use App\Services\Admin\PlatformAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AdminPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_disabled_accounts_cannot_log_in_and_inactive_sessions_are_revoked(): void
    {
        $disabledUser = User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => 'CorrectPass9!AB',
        ]);
        $disabledUser->forceFill(['is_active' => false])->save();

        $this->from('/login')->post('/login', [
            'email' => $disabledUser->email,
            'password' => 'CorrectPass9!AB',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($disabledUser)
            ->get('/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('platform_audit_events', [
            'actor_id' => $disabledUser->id,
            'event_key' => 'auth.inactive_session_revoked',
        ]);
    }

    public function test_administrator_can_disable_another_account_and_the_change_is_audited(): void
    {
        $administrator = $this->createAdministrator('admin@example.com');
        $user = User::factory()->create(['email' => 'member@example.com']);
        $previousRememberToken = $user->remember_token;

        $this->actingAs($administrator)
            ->put(route('admin.users.status.update', $user), ['is_active' => '0'])
            ->assertRedirect();

        $this->assertFalse($user->fresh()->is_active);
        $this->assertNotSame($previousRememberToken, $user->fresh()->remember_token);

        $event = PlatformAuditEvent::query()
            ->where('event_key', 'admin.user_status_updated')
            ->firstOrFail();

        $this->assertSame((string) $user->id, $event->subject_id);
        $this->assertSame(['old_active' => true, 'new_active' => false], $event->metadata);
        $this->assertSame(UserRole::Administrator, $administrator->fresh()->role);
    }

    public function test_administrators_cannot_change_their_own_role_or_status(): void
    {
        $administrator = $this->createAdministrator('admin@example.com');

        $this->actingAs($administrator)
            ->put(route('admin.users.status.update', $administrator), ['is_active' => '0'])
            ->assertForbidden();

        $this->put(route('admin.users.role.update', $administrator), ['role' => 'user'])
            ->assertForbidden();

        $this->assertTrue($administrator->fresh()->is_active);
        $this->assertSame(UserRole::Administrator, $administrator->fresh()->role);
    }

    public function test_disabling_another_administrator_leaves_one_active_administrator(): void
    {
        $administrator = $this->createAdministrator('admin@example.com');
        $otherAdministrator = $this->createAdministrator('other-admin@example.com');

        $this->actingAs($administrator)
            ->put(route('admin.users.status.update', $otherAdministrator), ['is_active' => '0'])
            ->assertRedirect();

        $this->assertFalse($otherAdministrator->fresh()->is_active);
        $this->assertSame(
            1,
            User::query()->where('role', UserRole::Administrator->value)->where('is_active', true)->count(),
        );
    }

    public function test_non_administrators_cannot_open_platform_administration_pages(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($user->is_active);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.scans.index'))->assertForbidden();
        $this->get(route('admin.file-checks.index'))->assertForbidden();
        $this->get(route('admin.activity.index'))->assertForbidden();
        $this->get(route('admin.configuration'))->assertForbidden();
        $this->get(route('admin.academy.progress'))->assertForbidden();
    }

    public function test_admin_reports_use_real_owner_linked_records_and_hide_private_file_keys_and_chat_content(): void
    {
        $administrator = $this->createAdministrator('admin@example.com');
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $asset = $owner->securityAssets()->create([
            'hostname' => 'owned.example.test',
            'verification_status' => AssetVerificationStatus::Verified,
            'authorization_attested_at' => now(),
            'verified_at' => now(),
        ]);
        $scanRun = $asset->scanRuns()->create([
            'user_id' => $owner->id,
            'scan_type' => ScanType::WebsiteAudit,
            'status' => ScanStatus::Completed,
            'requested_at' => now(),
            'started_at' => now(),
            'finished_at' => now(),
        ]);
        $scanRun->findings()->create([
            'user_id' => $owner->id,
            'rule_key' => 'admin-report-test',
            'title' => 'Observed test finding',
            'description' => 'A persisted finding for the admin report test.',
            'recommended_action' => 'Review the configuration.',
            'severity' => SecurityFindingSeverity::Low,
            'verification_state' => FindingVerificationState::Unverified,
            'state' => SecurityFindingState::Open,
            'evidence' => ['status_code' => 200],
            'detected_at' => now(),
        ]);
        $fileCheck = $owner->fileChecks()->create([
            'storage_key' => $owner->id.'/private-report.bin',
            'original_name' => 'report-private.bin',
            'size_bytes' => 1024,
            'mime_type' => 'application/octet-stream',
            'sha256' => str_repeat('a', 64),
            'status' => 'completed',
            'processed_at' => now(),
        ]);
        $owner->agentThreads()->create([
            'title' => 'PRIVATE_USER_CHAT_TITLE',
            'last_activity_at' => now(),
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Platform administration')
            ->assertDontSee('PRIVATE_USER_CHAT_TITLE');

        $this->get(route('admin.scans.index'))
            ->assertOk()
            ->assertSee('owner@example.com')
            ->assertSee('owned.example.test');

        $this->get(route('admin.scans.show', $scanRun))
            ->assertOk()
            ->assertSee('owner@example.com')
            ->assertSee('Website audit')
            ->assertSee('Observed test finding')
            ->assertSee('status_code');

        $this->get(route('admin.file-checks.index'))
            ->assertOk()
            ->assertSee('owner@example.com')
            ->assertSee('report-private.bin');

        $this->get(route('admin.file-checks.show', $fileCheck))
            ->assertOk()
            ->assertSee(str_repeat('a', 64))
            ->assertDontSee($fileCheck->storage_key)
            ->assertSee('No malware scan was performed');
        $this->get(route('admin.users.index'))->assertOk();

        $this->assertSame(1, PlatformAuditEvent::query()->where('event_key', 'admin.scan_run_viewed')->count());
        $this->assertSame(1, PlatformAuditEvent::query()->where('event_key', 'admin.file_check_viewed')->count());
        $this->assertDatabaseHas('platform_audit_events', [
            'event_key' => 'admin.user_list_viewed',
        ]);
    }

    public function test_academy_progress_is_owner_linked_without_rendering_quiz_answer_details(): void
    {
        $administrator = $this->createAdministrator('admin@example.com');
        $owner = User::factory()->create(['email' => 'learner@example.com']);
        $category = AcademyCategory::query()->create([
            'name' => 'Fundamentals',
            'slug' => 'fundamentals',
            'sort_order' => 1,
            'is_published' => true,
        ]);
        $course = AcademyCourse::query()->create([
            'academy_category_id' => $category->id,
            'title' => 'Security basics',
            'slug' => 'security-basics',
            'summary' => 'A test course.',
            'level' => 'beginner',
            'estimated_minutes' => 10,
            'sort_order' => 1,
            'is_published' => true,
        ]);
        $lesson = AcademyLesson::query()->create([
            'academy_course_id' => $course->id,
            'title' => 'Account protection',
            'slug' => 'account-protection',
            'content' => 'Original lesson text.',
            'sort_order' => 1,
            'is_published' => true,
        ]);
        $quiz = AcademyQuiz::query()->create([
            'academy_lesson_id' => $lesson->id,
            'title' => 'Knowledge check',
            'pass_percentage' => 70,
            'is_published' => true,
        ]);
        AcademyLessonProgress::query()->create([
            'user_id' => $owner->id,
            'academy_lesson_id' => $lesson->id,
            'last_viewed_at' => now(),
            'completed_at' => now(),
        ]);
        AcademyQuizAttempt::query()->create([
            'user_id' => $owner->id,
            'academy_quiz_id' => $quiz->id,
            'score' => 1,
            'question_count' => 1,
            'passed' => true,
            'results' => [['question_id' => 99, 'submitted_option' => 'A', 'correct' => true]],
            'submitted_at' => now(),
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.academy.progress'))
            ->assertOk()
            ->assertSee('learner@example.com')
            ->assertSee('Security basics')
            ->assertSee('Account protection')
            ->assertSee('Passed')
            ->assertDontSee('submitted_option');
    }

    public function test_academy_content_mutations_are_audited_without_storing_content(): void
    {
        $administrator = $this->createAdministrator('admin@example.com');

        $this->actingAs($administrator)
            ->post(route('admin.academy.categories.store'), [
                'name' => 'Private title not copied to the audit event',
                'slug' => 'audit-test-category',
                'description' => 'Private content not copied to the audit event.',
                'sort_order' => 1,
                'is_published' => '1',
            ])
            ->assertRedirect();

        $event = PlatformAuditEvent::query()
            ->where('event_key', 'admin.academy_category_created')
            ->firstOrFail();

        $this->assertSame(
            ['content_type' => 'category', 'new_published' => true],
            $event->metadata,
        );
        $this->assertStringNotContainsString('Private title', json_encode($event->metadata));
    }

    public function test_configuration_page_shows_status_without_revealing_secrets_or_claiming_connectivity(): void
    {
        $administrator = $this->createAdministrator('admin@example.com');
        config([
            'services.openrouter.api_key' => 'test-openrouter-secret',
            'safe_navigation.providers.virustotal.credential' => 'test-reputation-secret',
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.configuration'))
            ->assertOk()
            ->assertSee('Credential present')
            ->assertSee('Configured · not tested')
            ->assertDontSee('test-openrouter-secret')
            ->assertDontSee('test-reputation-secret');
    }

    public function test_audit_logger_rejects_unapproved_metadata_fields(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PlatformAuditLogger)->record(
            User::factory()->create(),
            'admin.test_event',
            metadata: ['prompt' => 'private content'],
        );
    }

    private function createAdministrator(string $email): User
    {
        $administrator = User::factory()->create(['email' => $email]);
        $administrator->forceFill(['role' => UserRole::Administrator])->save();

        return $administrator->fresh();
    }
}
