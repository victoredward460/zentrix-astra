<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePlatformFeatureEnabled;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\TrustHostsForHealthChecks;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustHosts as LaravelTrustHosts;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'platform.feature' => EnsurePlatformFeatureEnabled::class,
            'role' => EnsureUserHasRole::class,
        ]);
        $middleware->replace(LaravelTrustHosts::class, TrustHostsForHealthChecks::class);
        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => route('dashboard'));
        $middleware->trustHosts(
            at: fn () => array_values(array_unique([
                '^'.preg_quote((string) parse_url((string) config('app.url'), PHP_URL_HOST)).'$',
                '^127\.0\.0\.1$',
                '^localhost$',
            ])),
            subdomains: false,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
