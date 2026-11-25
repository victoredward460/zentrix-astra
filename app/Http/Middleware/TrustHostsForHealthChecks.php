<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;

class TrustHostsForHealthChecks extends TrustHosts
{
    public function handle(Request $request, $next)
    {
        $isPublicHealthPath = $request->path() === '/' || $request->is('up');

        if (in_array($request->method(), ['GET', 'HEAD'], true) && $isPublicHealthPath) {
            // These public pages are safe for internal probes with non-public Host headers.
            Request::setTrustedHosts([]);

            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
