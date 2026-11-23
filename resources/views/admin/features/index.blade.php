@extends('layouts.app')

@section('title', 'Platform controls')

@section('workspace')
    <div class="workspace-content grid">
        <header class="workspace-heading">
            <div>
                <p class="eyebrow">ADMINISTRATION / PLATFORM CONTROLS</p>
                <h1>Platform controls</h1>
                <p class="workspace-intro">Manage account creation and user access to platform modules. Changes take effect immediately and are recorded in the audit trail.</p>
            </div>
            <a class="button button-outline button-small" href="{{ route('admin.dashboard') }}">Back to administration</a>
        </header>

        @if (session('status'))
            <p class="form-notice" role="status">{{ session('status') }}</p>
        @endif
        @error('enabled')
            <p class="form-error" role="alert">{{ $message }}</p>
        @enderror

        <x-panel class="platform-controls-note">
            <span class="safety-icon" aria-hidden="true">
                <svg viewBox="0 0 20 20"><path d="M10 2.5 16 5v4.7c0 3.5-2.5 6.1-6 7.8-3.5-1.7-6-4.3-6-7.8V5l6-2.5Z" /><path d="m7.5 9.8 1.7 1.7 3.5-3.7" /></svg>
            </span>
            <div>
                <strong>Protected controls, with a recovery path</strong>
                <p>Disabling a module blocks ordinary accounts; administrators retain access so they can verify or re-enable it. Public registration also respects the deployment-level AUTH_ALLOW_REGISTRATION setting.</p>
            </div>
        </x-panel>

        <section class="platform-feature-grid" aria-label="Platform feature controls">
            @foreach ($features as $feature)
                <x-panel class="platform-feature-card">
                    <div class="platform-feature-heading">
                        <div>
                            <p class="eyebrow">{{ $feature['key'] === 'public-registration' ? 'ACCOUNT ACCESS' : 'MODULE ACCESS' }}</p>
                            <h2>{{ $feature['label'] }}</h2>
                        </div>
                        <x-status-badge :tone="$feature['enabled'] ? 'good' : 'muted'">
                            {{ $feature['enabled'] ? 'Enabled' : 'Disabled' }}
                        </x-status-badge>
                    </div>
                    <p class="platform-feature-description">{{ $feature['description'] }}</p>

                    @if ($feature['key'] === 'public-registration' && ! $feature['environment_allows_enable'])
                        <p class="platform-feature-lock">Locked off by the deployment environment. Change AUTH_ALLOW_REGISTRATION in the private environment settings to permit public registration.</p>
                    @endif

                    <form method="POST" action="{{ route('admin.features.update', $feature['key']) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="enabled" value="{{ $feature['enabled'] ? '0' : '1' }}">
                        <button
                            class="button {{ $feature['enabled'] ? 'button-outline' : 'button-primary' }} button-small"
                            type="submit"
                            @disabled($feature['key'] === 'public-registration' && ! $feature['environment_allows_enable'] && ! $feature['enabled'])
                        >
                            {{ $feature['enabled'] ? 'Disable for users' : 'Enable for users' }}
                        </button>
                    </form>
                </x-panel>
            @endforeach
        </section>
    </div>
@endsection
