@extends('layouts.app')

@section('title', 'Account profile')

@section('workspace')
    <div class="workspace-content grid">
        <div class="workspace-heading">
            <div>
                <p class="eyebrow">ACCOUNT / PROFILE</p>
                <h1>Your profile</h1>
                <p class="workspace-intro">Manage your account details and password.</p>
            </div>
        </div>

        @if (session('status'))
            <p class="form-notice" role="status">{{ session('status') }}</p>
        @endif
        @if (session('password-status'))
            <p class="form-notice" role="status">{{ session('password-status') }}</p>
        @endif
        @if (session('ai-preferences-status'))
            <p class="form-notice" role="status">{{ session('ai-preferences-status') }}</p>
        @endif

        <div class="profile-grid">
            <x-panel class="profile-panel">
                <p class="eyebrow">PERSONAL DETAILS</p>
                <h2>Account information</h2>
                <form class="auth-form" method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PUT')
                    <label class="auth-field" for="name">
                        <span>Name</span>
                        <input class="auth-input" id="name" name="name" type="text"
                            value="{{ old('name', auth()->user()->name) }}" maxlength="255" required>
                        @error('name') <span class="form-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="auth-field" for="email">
                        <span>Email address</span>
                        <input class="auth-input" id="email" name="email" type="email"
                            value="{{ old('email', auth()->user()->email) }}" maxlength="255" required>
                        @error('email') <span class="form-error">{{ $message }}</span> @enderror
                    </label>
                    <div class="profile-role">
                        <span>Access role</span>
                        <strong>{{ auth()->user()->role->value === 'admin' ? 'Administrator' : 'User' }}</strong>
                        <small>Only an authorized administrator can change account roles.</small>
                    </div>
                    <button class="button button-primary" type="submit">Save profile</button>
                </form>
            </x-panel>

            <x-panel class="profile-panel">
                <p class="eyebrow">AI PREFERENCES</p>
                <h2>AI privacy</h2>
                <form class="auth-form" method="POST" action="{{ route('profile.ai-preferences.update') }}">
                    @csrf
                    @method('PUT')
                    <label class="scanner-attestation">
                        <input type="checkbox" name="ai_provider_consent" value="1" @checked(old('ai_provider_consent', auth()->user()->hasAiProviderConsent()))>
                        <span>Enable AI features for this account. Prompts and selected context may be sent to OpenRouter and NVIDIA. The provider may retain and use prompts for model training under its policies; strict zero-data-retention eligibility is not confirmed for these free endpoints. File explanations send minimized metadata only, never file bytes, filenames, hashes, or storage keys. Do not send secrets or sensitive personal data. Uncheck and save to turn provider sharing off.</span>
                    </label>
                    @error('ai_provider_consent') <span class="form-error">{{ $message }}</span> @enderror
                    <button class="button button-primary" type="submit">Save AI preferences</button>
                </form>
            </x-panel>

            <x-panel class="profile-panel">
                <p class="eyebrow">CREDENTIALS</p>
                <h2>Change password</h2>
                <form class="auth-form" method="POST" action="{{ route('profile.password.update') }}">
                    @csrf
                    @method('PUT')
                    <label class="auth-field" for="current_password">
                        <span>Current password</span>
                        <input class="auth-input" id="current_password" name="current_password" type="password"
                            autocomplete="current-password" required>
                        @error('current_password') <span class="form-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="auth-field" for="password">
                        <span>New password</span>
                        <input class="auth-input" id="password" name="password" type="password"
                            autocomplete="new-password" required>
                        <span class="form-hint">At least 12 characters with upper- and lowercase letters, a number, and a symbol.</span>
                        @error('password') <span class="form-error">{{ $message }}</span> @enderror
                    </label>
                    <label class="auth-field" for="password_confirmation">
                        <span>Confirm new password</span>
                        <input class="auth-input" id="password_confirmation" name="password_confirmation" type="password"
                            autocomplete="new-password" required>
                    </label>
                    <button class="button button-primary" type="submit">Update password</button>
                </form>
            </x-panel>
        </div>
    </div>
@endsection
