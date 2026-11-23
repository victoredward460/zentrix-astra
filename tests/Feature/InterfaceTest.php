<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_public_landing_page_presents_the_platform_preview(): void
    {
        $landingPage = $this->get('/');

        $landingPage
            ->assertOk()
            ->assertSee('Security work')
            ->assertSee('Install App')
            ->assertSee('manifest.webmanifest')
            ->assertSee('Passive scanning requires verified ownership and authorization.')
            ->assertSee('ADMIN ONLY / AVAILABLE')
            ->assertSee('ADMIN ONLY / PROVIDER-DEPENDENT')
            ->assertSee('FREE ROUTE · AVAILABILITY VARIES')
            ->assertSee('Security Scanner')
            ->assertSee('Defensive security, made deliberate.')
            ->assertSee('<script src="https://cdn.botpress.cloud/webchat/v5.0/inject.js"></script>', false)
            ->assertSee('<script src="https://files.bpcontent.cloud/2026/11/23/14/20261123142808-B5ROGMH5.js" defer></script>', false);

        $this->assertSame(
            1,
            substr_count($landingPage->getContent(), 'https://cdn.botpress.cloud/webchat/v5.0/inject.js'),
        );
        $this->assertSame(
            1,
            substr_count($landingPage->getContent(), 'https://files.bpcontent.cloud/2026/11/23/14/20261123142808-B5ROGMH5.js'),
        );

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<script src="https://cdn.botpress.cloud/webchat/v5.0/inject.js"></script>', false);
    }

    public function test_dashboard_has_scoped_empty_states_and_links_only_to_available_modules(): void
    {
        $user = User::factory()->create(['name' => 'Primary Account']);
        User::factory()->create(['name' => 'Other Private Account']);

        $dashboard = $this->actingAs($user)->get('/dashboard');
        $dashboard
            ->assertOk()
            ->assertSee('Security overview')
            ->assertSee('Welcome, Primary Account.')
            ->assertSee($user->created_at->format('F Y'))
            ->assertDontSee('Other Private Account')
            ->assertSee('Not assessed')
            ->assertSee('Completed scans')
            ->assertSee('File checks')
            ->assertSee('0 records')
            ->assertSee('No findings to review')
            ->assertSee('Your scan history is empty')
            ->assertSee('A chart would be misleading right now.')
            ->assertSee('database-health-loading')
            ->assertSee('Install App')
            ->assertSee('Connection unavailable')
            ->assertSee('aria-controls="app-sidebar"', false)
            ->assertSee('<script src="https://cdn.botpress.cloud/webchat/v5.0/inject.js"></script>', false)
            ->assertSee(route('scanner.assets.index'), false)
            ->assertSee(route('file-check.index'), false)
            ->assertSee('File Check')
            ->assertDontSee('href="/safe-navigation"', false)
            ->assertDontSee('href="/admin/users"', false);
    }
}
