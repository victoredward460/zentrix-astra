<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        $user = request()->user();
        $this->authorize('view', $user);

        return view('profile.edit');
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('status', 'Your profile has been updated.');
    }

    public function updateAiPreferences(Request $request): RedirectResponse
    {
        $request->validate(['ai_provider_consent' => ['sometimes', 'accepted']]);
        $user = $request->user();
        $consented = $request->boolean('ai_provider_consent');

        $user->forceFill([
            'ai_data_sharing_consent_at' => $consented
                ? ($user->ai_data_sharing_consent_at ?? now())
                : null,
        ])->save();

        return back()->with(
            'ai-preferences-status',
            $consented
                ? 'AI data-sharing preference saved. AI features are enabled for your account.'
                : 'Provider data sharing is turned off for your account.',
        );
    }

    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate();

        return back()->with('password-status', 'Your password has been updated.');
    }
}
