@extends('layouts.base')

@section('title', 'Sign in')

@section('content')
    <main class="auth-shell container">
        <a class="auth-brand" href="{{ route('home') }}"><x-brand /></a>
        <section class="auth-card panel" aria-labelledby="auth-title">
            <p class="eyebrow">SECURE ACCOUNT ACCESS</p>
            <h1 id="auth-title">Sign in</h1>
            <p class="auth-description">Use your ZENTRIX ASTRA account to open your private workspace.</p>

            @if (session('status'))
                <p class="form-notice" role="status">{{ session('status') }}</p>
            @endif

            <form class="auth-form" method="POST" action="{{ route('login.store') }}">
                @csrf
                <label class="auth-field" for="email">
                    <span>Email address</span>
                    <input class="auth-input" id="email" name="email" type="email" value="{{ old('email') }}"
                        autocomplete="username" required autofocus>
                    @error('email') <span class="form-error">{{ $message }}</span> @enderror
                </label>

                <label class="auth-field" for="password">
                    <span>Password</span>
                    <input class="auth-input" id="password" name="password" type="password"
                        autocomplete="current-password" required>
                    @error('password') <span class="form-error">{{ $message }}</span> @enderror
                </label>

                <label class="auth-checkbox">
                    <input name="remember" type="checkbox" value="1">
                    <span>Remember me on this device</span>
                </label>

                <button class="button button-primary auth-submit" type="submit">Sign in securely</button>
            </form>

            <div class="auth-links">
                <a class="text-link" href="{{ route('password.request') }}">Forgot your password?</a>
                @if (config('auth.allow_registration'))
                    <span>New here? <a class="text-link" href="{{ route('register') }}">Create an account</a></span>
                @endif
            </div>
            <a class="auth-home-link" href="{{ route('home') }}">Back to platform overview</a>
        </section>
    </main>
@endsection
