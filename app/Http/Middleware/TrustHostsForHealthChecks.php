<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;

class TrustHostsForHealthChecks extends TrustHosts
{
    public function handle(Request $request, $next)
    {
        if (in_array($request->method(), ['GET', 'HEAD'], true) && $request->is('up')) {
            // The liveness endpoint is independent of database-backed web sessions.
            Request::setTrustedHosts([]);

            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
