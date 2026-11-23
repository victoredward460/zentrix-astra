<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustHostsForHealthChecks;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_endpoint_returns_success(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_liveness_endpoint_accepts_the_deployment_probe_host(): void
    {
        $this->withServerVariables(['HTTP_HOST' => 'health-probe.internal'])
            ->get('/up')
            ->assertOk();
    }

    public function test_loopback_hosts_are_trusted_without_allowing_unconfigured_hosts(): void
    {
        config(['app.url' => 'https://zentrix.example']);

        Request::setTrustedHosts(app(TrustHosts::class)->hosts());

        try {
            $this->assertSame('zentrix.example', Request::create('https://zentrix.example/index.php')->getHost());
            $this->assertSame('127.0.0.1', Request::create('http://127.0.0.1/index.php')->getHost());
            $this->assertSame('localhost', Request::create('http://localhost/index.php')->getHost());

            $this->expectException(SuspiciousOperationException::class);
            Request::create('http://untrusted.example/index.php')->getHost();
        } finally {
            Request::setTrustedHosts([]);
        }
    }

    public function test_only_the_liveness_endpoint_bypasses_trusted_host_validation(): void
    {
        $middleware = app(TrustHostsForHealthChecks::class);
        Request::setTrustedHosts(['^zentrix\.example$']);

        try {
            $healthResponse = $middleware->handle(
                Request::create('http://health-probe.internal/up'),
                fn (Request $request) => response($request->getHost()),
            );

            $this->assertSame('health-probe.internal', $healthResponse->getContent());

            Request::setTrustedHosts(['^zentrix\.example$']);

            $this->expectException(SuspiciousOperationException::class);
            $middleware->handle(
                Request::create('http://health-probe.internal/agent'),
                fn (Request $request) => response($request->getHost()),
            );
        } finally {
            Request::setTrustedHosts([]);
        }
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
