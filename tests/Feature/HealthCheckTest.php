<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_endpoint_returns_success(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_readiness_endpoint_checks_the_database(): void
    {
        $this->getJson('/health/ready')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'checks' => [
                    'database' => 'ok',
                ],
            ]);
    }

    public function test_readiness_endpoint_hides_database_errors_and_logs_the_failure(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andThrow(new PDOException('private connection detail'));

        Log::shouldReceive('warning')
            ->once()
            ->with('Database readiness check failed.', [
                'exception' => PDOException::class,
            ]);

        $this->getJson('/health/ready')
            ->assertServiceUnavailable()
            ->assertExactJson([
                'status' => 'unavailable',
                'checks' => [
                    'database' => 'unavailable',
                ],
            ])
            ->assertDontSee('private connection detail');
    }

    public function test_user_model_can_persist_records(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }
}
