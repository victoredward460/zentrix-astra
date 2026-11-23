<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Admin\PlatformAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, PlatformAuditLogger $auditLogger): RedirectResponse
    {
        if (! Auth::attempt(
            $request->only('email', 'password'),
            $request->boolean('remember'),
        )) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! $request->user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();
        $auditLogger->record($request->user(), 'auth.login_succeeded');

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, PlatformAuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($user !== null) {
            $auditLogger->record($user, 'auth.logout');
        }

        return redirect()->route('home');
    }
}
