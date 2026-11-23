@extends('layouts.base')

@section('title', 'Choose a new password')

@section('content')
    <main class="auth-shell container">
        <a class="auth-brand" href="{{ route('home') }}"><x-brand /></a>
        <section class="auth-card panel" aria-labelledby="auth-title">
            <p class="eyebrow">ACCOUNT RECOVERY</p>
            <h1 id="auth-title">Choose a new password</h1>
            <p class="auth-description">Reset links expire after 60 minutes and can only be used once.</p>

            <form class="auth-form" method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <label class="auth-field" for="email">
                    <span>Email address</span>
                    <input class="auth-input" id="email" name="email" type="email" value="{{ old('email', $email) }}"
                        autocomplete="email" required autofocus>
                    @error('email') <span class="form-error">{{ $message }}</span> @enderror
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

                <button class="button button-primary auth-submit" type="submit">Update password</button>
            </form>
            <a class="auth-home-link" href="{{ route('login') }}">Return to sign in</a>
        </section>
    </main>
@endsection
