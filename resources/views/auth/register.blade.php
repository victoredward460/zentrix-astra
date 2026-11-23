@extends('layouts.base')

@section('title', 'Create an account')

@section('content')
    <main class="auth-shell container">
        <a class="auth-brand" href="{{ route('home') }}"><x-brand /></a>
        <section class="auth-card panel" aria-labelledby="auth-title">
            <p class="eyebrow">PERSONAL WORKSPACE</p>
            <h1 id="auth-title">Create your account</h1>
            <p class="auth-description">New accounts receive ordinary-user access. Administrator access is provisioned separately.</p>

            <form class="auth-form" method="POST" action="{{ route('register.store') }}">
                @csrf
                <label class="auth-field" for="name">
                    <span>Name</span>
                    <input class="auth-input" id="name" name="name" type="text" value="{{ old('name') }}"
                        autocomplete="name" maxlength="255" required autofocus>
                    @error('name') <span class="form-error">{{ $message }}</span> @enderror
                </label>

                <label class="auth-field" for="email">
                    <span>Email address</span>
                    <input class="auth-input" id="email" name="email" type="email" value="{{ old('email') }}"
                        autocomplete="email" maxlength="255" required>
                    @error('email') <span class="form-error">{{ $message }}</span> @enderror
                </label>

                <label class="auth-field" for="password">
                    <span>Password</span>
                    <input class="auth-input" id="password" name="password" type="password"
                        autocomplete="new-password" required>
                    <span class="form-hint">At least 12 characters with upper- and lowercase letters, a number, and a symbol.</span>
                    @error('password') <span class="form-error">{{ $message }}</span> @enderror
                </label>

                <label class="auth-field" for="password_confirmation">
                    <span>Confirm password</span>
                    <input class="auth-input" id="password_confirmation" name="password_confirmation" type="password"
                        autocomplete="new-password" required>
                </label>

                <button class="button button-primary auth-submit" type="submit">Create account</button>
            </form>

            <div class="auth-links">
                <span>Already registered? <a class="text-link" href="{{ route('login') }}">Sign in</a></span>
            </div>
            <a class="auth-home-link" href="{{ route('home') }}">Back to platform overview</a>
        </section>
    </main>
@endsection
