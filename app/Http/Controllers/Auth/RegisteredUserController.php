<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisteredUserRequest;
use App\Models\User;
use App\Services\Admin\PlatformFeatureService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(PlatformFeatureService $features): View
    {
        abort_unless($features->isEnabled('public-registration'), 404);

        return view('auth.register');
    }

    public function store(RegisteredUserRequest $request, PlatformFeatureService $features): RedirectResponse
    {
        abort_unless($features->isEnabled('public-registration'), 404);

        $user = User::query()->create($request->safe()->only(['name', 'email', 'password']));

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
