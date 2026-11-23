<?php

namespace App\Http\Middleware;

use App\Services\Admin\PlatformAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function __construct(
        private readonly PlatformAuditLogger $auditLogger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->is_active) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $this->auditLogger->record($user, 'auth.inactive_session_revoked');

        return redirect()
            ->route('login')
            ->withErrors(['email' => 'This account is currently disabled.']);
    }
}
