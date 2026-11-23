@extends('layouts.base')

@section('title', 'Reset your password')

@section('content')
    <main class="auth-shell container">
        <a class="auth-brand" href="{{ route('home') }}"><x-brand /></a>
        <section class="auth-card panel" aria-labelledby="auth-title">
            <p class="eyebrow">ACCOUNT RECOVERY</p>
            <h1 id="auth-title">Reset your password</h1>
            <p class="auth-description">Enter your account email. Reset requests are rate limited, and the same response is shown whether an account exists or not.</p>

            @if (session('status'))
                <p class="form-notice" role="status">{{ session('status') }}</p>
            @endif

            <form class="auth-form" method="POST" action="{{ route('password.email') }}">
                @csrf
                <label class="auth-field" for="email">
                    <span>Email address</span>
                    <input class="auth-input" id="email" name="email" type="email" value="{{ old('email') }}"
                        autocomplete="email" required autofocus>
                    @error('email') <span class="form-error">{{ $message }}</span> @enderror
                </label>
                <button class="button button-primary auth-submit" type="submit">Send reset link</button>
            </form>

            <a class="auth-home-link" href="{{ route('login') }}">Return to sign in</a>
        </section>
    </main>
@endsection
