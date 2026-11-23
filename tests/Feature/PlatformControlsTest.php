<?php

namespace Tests\Feature;

use App\Models\PlatformFeatureSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['auth.allow_registration' => true]);
    }

    public function test_administrator_can_disable_user_module_access_and_the_change_is_audited(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $this->actingAs($administrator)
            ->get(route('admin.features.index'))
            ->assertOk()
            ->assertSee('Platform controls')
            ->assertSee('Security Scanner');

        $this->actingAs($administrator)
            ->patch(route('admin.features.update', 'file-check'), ['enabled' => '0'])
            ->assertRedirect(route('admin.features.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('platform_feature_settings', [
            'feature_key' => 'file-check',
            'enabled' => false,
            'updated_by' => $administrator->id,
        ]);
        $this->assertDatabaseHas('platform_audit_events', [
            'actor_id' => $administrator->id,
            'event_key' => 'admin.feature_access_changed',
        ]);

        $event = \App\Models\PlatformAuditEvent::query()
            ->where('event_key', 'admin.feature_access_changed')
            ->firstOrFail();
        $this->assertSame(['feature_key' => 'file-check', 'new_enabled' => false], $event->metadata);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('TEMPORARILY PAUSED')
            ->assertSee('PAUSED');
        $this->actingAs($user)->get(route('file-check.index'))->assertStatus(503);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('page-intro')
            ->assertSee('File Check, Temporarily paused', false);
        $this->actingAs($administrator)->get(route('file-check.index'))->assertOk();
    }

    public function test_public_registration_can_be_disabled_and_reenabled_by_an_administrator(): void
    {
        $administrator = User::factory()->create(['role' => 'admin']);

        $this->get(route('register'))->assertOk();

        $this->actingAs($administrator)
            ->patch(route('admin.features.update', 'public-registration'), ['enabled' => '0'])
            ->assertRedirect(route('admin.features.index'));

        $this->post(route('logout'))->assertRedirect();
        $this->get(route('register'))->assertNotFound();

        $this->actingAs($administrator)
            ->patch(route('admin.features.update', 'public-registration'), ['enabled' => '1'])
            ->assertRedirect(route('admin.features.index'));

        $this->post(route('logout'))->assertRedirect();
        $this->get(route('register'))->assertOk();
    }

    public function test_deployment_environment_can_lock_public_registration_off(): void
    {
        config(['auth.allow_registration' => false]);
        $administrator = User::factory()->create(['role' => 'admin']);
        PlatformFeatureSetting::query()->where('feature_key', 'public-registration')->update(['enabled' => false]);

        $this->actingAs($administrator)
            ->patch(route('admin.features.update', 'public-registration'), ['enabled' => '1'])
            ->assertRedirect()
            ->assertSessionHasErrors('enabled');

        $this->post(route('logout'))->assertRedirect();
        $this->get(route('register'))->assertNotFound();
        $this->assertDatabaseHas('platform_feature_settings', [
            'feature_key' => 'public-registration',
            'enabled' => false,
        ]);
    }

    public function test_regular_users_cannot_open_platform_controls_or_update_features(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.features.index'))
            ->assertForbidden();

        $this->patch(route('admin.features.update', 'scanner'), ['enabled' => '0'])
            ->assertForbidden();

        $this->assertDatabaseMissing('platform_feature_settings', [
            'feature_key' => 'scanner',
            'enabled' => false,
        ]);
    }
}
