<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_cannot_access_the_workspace_or_administrator_routes(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/safe-navigation')->assertRedirect(route('login'));
        $this->get('/admin/users')->assertRedirect(route('login'));
    }

    public function test_ordinary_user_is_denied_administrator_routes_and_other_profiles(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Account information');
        $this->actingAs($user)->get('/safe-navigation')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();

        $this->assertTrue(Gate::forUser($user)->allows('view', $user));
        $this->assertFalse(Gate::forUser($user)->allows('view', $otherUser));
    }

    public function test_administrator_can_access_protected_admin_pages_and_promote_a_user(): void
    {
        $administrator = $this->createAdministrator('admin@example.com');
        $user = User::factory()->create(['email' => 'member@example.com']);

        $this->actingAs($administrator)
            ->get('/safe-navigation')
            ->assertOk()
            ->assertSee('Reputation sources')
            ->assertSee('Configured · not tested')
            ->assertSee('Submitted destinations are never opened.');

        $this->get('/admin/users')->assertOk()->assertSee('member@example.com');
        $this->get('/dashboard')->assertOk()->assertSee(route('admin.users.index'), false);

        $this->put("/admin/users/{$user->id}/role", ['role' => 'admin'])
            ->assertRedirect();

        $this->assertSame(UserRole::Administrator, $user->fresh()->role);
        $this->assertDatabaseHas('platform_audit_events', [
            'actor_id' => $administrator->id,
            'event_key' => 'admin.user_role_updated',
            'subject_id' => (string) $user->id,
        ]);
    }

    public function test_administrator_cannot_change_own_role_or_demote_the_last_administrator(): void
    {
        $administrator = $this->createAdministrator('admin@example.com');

        $this->actingAs($administrator)
            ->put("/admin/users/{$administrator->id}/role", ['role' => 'user'])
            ->assertForbidden();

        $this->assertSame(UserRole::Administrator, $administrator->fresh()->role);
    }

    private function createAdministrator(string $email): User
    {
        $administrator = User::factory()->create(['email' => $email]);
        $administrator->forceFill(['role' => UserRole::Administrator])->save();

        return $administrator->fresh();
    }
}
