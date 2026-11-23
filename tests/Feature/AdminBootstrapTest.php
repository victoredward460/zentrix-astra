<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_interactive_bootstrap_creates_the_first_admin_without_a_default_password(): void
    {
        $password = 'BootStrapPass9!AB';

        $this->artisan('zentrix:bootstrap-admin')
            ->expectsQuestion('Administrator name', 'First Administrator')
            ->expectsQuestion('Administrator email', 'first.admin@example.com')
            ->expectsQuestion('Administrator password (hidden input)', $password)
            ->expectsQuestion('Confirm administrator password', $password)
            ->expectsConfirmation('Create the first administrator now?', 'yes')
            ->expectsOutput('The administrator account was created. Sign in through the application login page.')
            ->assertExitCode(0);

        $administrator = User::query()->where('email', 'first.admin@example.com')->firstOrFail();

        $this->assertSame(UserRole::Administrator, $administrator->role);
        $this->assertTrue(Hash::check($password, $administrator->password));
    }

    public function test_bootstrap_refuses_to_run_after_an_administrator_exists(): void
    {
        $administrator = User::factory()->create();
        $administrator->forceFill(['role' => UserRole::Administrator])->save();

        $this->artisan('zentrix:bootstrap-admin')
            ->expectsOutput('An administrator already exists. This bootstrap command can only create the first one.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 1);
    }
}
