<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AI\OpenRouterEndpointDiscovery;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_an_ordinary_user_and_authenticates_the_session(): void
    {
        $this->post('/register', [
            'name' => 'Astra User',
            'email' => 'ASTRA.USER@example.com',
            'password' => 'SecurePass9!AB',
            'password_confirmation' => 'SecurePass9!AB',
            'role' => 'admin',
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'astra.user@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::User, $user->role);
        $this->assertTrue(Hash::check('SecurePass9!AB', $user->password));
    }

    public function test_registration_can_be_disabled_without_changing_existing_accounts(): void
    {
        config(['auth.allow_registration' => false]);

        $this->get('/register')->assertNotFound();

        $this->post('/register', [
            'name' => 'Astra User',
            'email' => 'user@example.com',
            'password' => 'SecurePass9!AB',
            'password_confirmation' => 'SecurePass9!AB',
        ])->assertNotFound();
    }

    public function test_login_authenticates_and_logout_ends_the_session(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => 'CorrectPass9!AB',
        ]);

        $this->get('/login');
        $guestSessionId = $this->app['session']->driver()->getId();

        $this->post('/login', [
            'email' => 'MEMBER@example.com',
            'password' => 'CorrectPass9!AB',
        ])->assertRedirect(route('dashboard'));

        $authenticatedSessionId = $this->app['session']->driver()->getId();
        $this->assertNotSame($guestSessionId, $authenticatedSessionId);
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('home'));

        $this->assertNotSame($authenticatedSessionId, $this->app['session']->driver()->getId());
        $this->assertGuest();
    }

    public function test_invalid_login_is_rejected_and_throttled(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post('/login', [
                'email' => 'member@example.com',
                'password' => 'incorrect',
            ])->assertRedirect('/login');
        }

        $this->post('/login', [
            'email' => 'member@example.com',
            'password' => 'incorrect',
        ])->assertTooManyRequests();

        $this->assertGuest();
    }

    public function test_password_reset_mail_is_sent_without_disclosing_account_existence_and_resets_the_hash(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'member@example.com']);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status', 'If an account exists and the request is eligible, password-reset instructions will be sent. Check the address and try again later if you requested a link recently.');

        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::createToken($user);
        $newPassword = 'ChangedPass9!AB';

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
    }

    public function test_password_reset_request_uses_the_same_response_for_unknown_email(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'unknown@example.com'])
            ->assertSessionHas('status', 'If an account exists and the request is eligible, password-reset instructions will be sent. Check the address and try again later if you requested a link recently.');

        Notification::assertNothingSent();
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'CurrentPass9!AB']);

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'incorrect',
            'password' => 'ChangedPass9!AB',
            'password_confirmation' => 'ChangedPass9!AB',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('CurrentPass9!AB', $user->fresh()->password));
    }

    public function test_profile_updates_are_self_scoped_and_cannot_change_the_user_role(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $this->actingAs($user)->put('/profile', [
            'name' => 'Updated Name',
            'email' => 'UPDATED@example.com',
            'role' => 'admin',
        ])->assertRedirect();

        $updated = $user->fresh();
        $this->assertSame('Updated Name', $updated->name);
        $this->assertSame('updated@example.com', $updated->email);
        $this->assertSame(UserRole::User, $updated->role);
        $this->assertNull($updated->email_verified_at);
    }

    public function test_chat_model_selection_and_one_time_provider_consent_are_separate(): void
    {
        config([
            'services.openrouter.model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
        ]);
        $this->mock(OpenRouterEndpointDiscovery::class, function ($mock): void {
            $mock->shouldReceive('eligibleProviders')->andReturn(['nvidia']);
        });
        $user = User::factory()->create();

        $this->assertSame('nvidia/nemotron-3-ultra-550b-a55b:free', $user->aiModel());
        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('AI privacy')
            ->assertDontSee('name="ai_model"', false)
            ->assertSee('name="ai_provider_consent"', false);
        $this->get(route('agent.index'))
            ->assertOk()
            ->assertSee('name="model"', false)
            ->assertSee('nvidia/nemotron-3-ultra-550b-a55b:free', false)
            ->assertSee('nvidia/nemotron-3.5-super-vl:free', false)
            ->assertSee('NVIDIA Nemotron 3.5 Super VL · Free')
            ->assertSee('AI is disabled until enabled once');

        $this->actingAs($user)
            ->put(route('profile.ai-preferences.update'), [
                'ai_provider_consent' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('ai-preferences-status');

        $this->patch(route('agent.model.update'), [
            'model' => 'nvidia/nemotron-3.5-super-vl:free',
        ])->assertOk()
            ->assertJsonPath('model', 'nvidia/nemotron-3.5-super-vl:free');

        $user->refresh();
        $this->assertSame('nvidia/nemotron-3.5-super-vl:free', $user->aiModel());
        $this->assertTrue($user->hasAiProviderConsent());

        $this->put(route('profile.ai-preferences.update'), [])->assertRedirect();

        $user->refresh();
        $this->assertSame('nvidia/nemotron-3.5-super-vl:free', $user->aiModel());
        $this->assertFalse($user->hasAiProviderConsent());

        $this->patch(route('agent.model.update'), [
            'model' => 'example/paid-model',
        ])->assertSessionHasErrors('model');
    }
}
