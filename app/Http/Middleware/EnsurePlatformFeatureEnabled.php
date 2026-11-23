<?php

namespace App\Http\Middleware;

use App\Services\Admin\PlatformFeatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformFeatureEnabled
{
    public function __construct(private readonly PlatformFeatureService $features) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $this->features->isEnabled($feature) && ! $request->user()?->isAdministrator()) {
            return response()->view('errors.feature-disabled', status: Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $next($request);
    }
}
