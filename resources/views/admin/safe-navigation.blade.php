@extends('layouts.app')

@section('title', 'Safe Navigation')

@section('workspace')
    <div class="workspace-content safe-navigation-content">
        <header class="workspace-heading">
            <div>
                <p class="eyebrow">ADMINISTRATION / RESTRICTED AREA</p>
                <h1>Safe Navigation</h1>
                <p class="workspace-intro">Review submitted URLs against reputation sources when approved integrations are connected. Submitted destinations are never opened.</p>
            </div>
        </header>

        @if (session('status'))
            <div class="form-notice safe-navigation-notice" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="form-error safe-navigation-error" role="alert">
                <strong>We could not record that URL.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="safe-navigation-panels">
            <x-panel class="safe-navigation-submit-panel">
                <p class="eyebrow">URL REPUTATION LOOKUP</p>
                <h2>Submit a URL</h2>
                <p>The URL is normalized and stored in your administrator history. Configured reputation providers receive the submitted URL; the application does not open or follow the destination.</p>
                <form method="POST" action="{{ route('safe-navigation.checks.store') }}" class="safe-navigation-form">
                    @csrf
                    <label for="url">HTTP or HTTPS URL</label>
                    <input id="url" name="url" type="url" inputmode="url" autocomplete="url"
                        value="{{ old('url') }}" maxlength="2048" placeholder="https://example.com/path" required>
                    <label class="safe-navigation-attestation">
                        <input type="checkbox" name="authorization_confirmed" value="1" @checked(old('authorization_confirmed')) required>
                        <span>I am authorized to submit this URL for a reputation check.</span>
                    </label>
                    <label class="safe-navigation-attestation">
                        <input type="checkbox" name="provider_disclosure_confirmed" value="1" @checked(old('provider_disclosure_confirmed')) required>
                        <span>I understand that the normalized URL (including its path and query) will be shared with every reputation provider that has credentials configured.</span>
                    </label>
                    <button class="button button-primary button-small" type="submit">Record URL assessment</button>
                </form>
            </x-panel>

            <x-panel class="safe-navigation-warning-panel">
                <span class="safety-icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20"><path d="M10 2.5 16 5v4.7c0 3.5-2.5 6.1-6 7.8-3.5-1.7-6-4.3-6-7.8V5l6-2.5Z" /><path d="M10 7v3.5m0 2.5h.01" /></svg>
                </span>
                <div>
                    <strong>Reputation results require careful interpretation</strong>
                    <p>Provider failures and missing credentials are reported separately. An unavailable or inconclusive result is not a clean result.</p>
                </div>
            </x-panel>
        </div>

        <section class="safe-navigation-provider-section" aria-labelledby="provider-status-heading">
            <div class="dashboard-section-heading">
                <div>
                    <p class="eyebrow">PROVIDER STATUS</p>
                    <h2 id="provider-status-heading">Reputation sources</h2>
                </div>
            </div>
            <div class="safe-navigation-provider-grid">
                @foreach ($providers as $provider)
                    <x-panel class="safe-navigation-provider-card">
                        <div>
                            <strong>{{ $provider['name'] }}</strong>
                            <x-status-badge :tone="$provider['state'] === 'configured_not_tested' ? 'accent' : 'muted'">
                                {{ $provider['state_label'] }}
                            </x-status-badge>
                        </div>
                        <p>
                            @if ($provider['state'] === 'configured_not_tested')
                                The adapter is ready to query this provider. This status page does not test network connectivity.
                            @elseif ($provider['state'] === 'adapter_unavailable')
                                A credential is present, but no supported adapter is available. No request is made.
                            @else
                                No credential is configured, so no request is made.
                            @endif
                        </p>
                        @if ($provider['credential_required'])
                            <small>Expected credential: <code>{{ $provider['credential_variable'] }}</code></small>
                        @endif
                    </x-panel>
                @endforeach
            </div>
        </section>

        <section class="scanner-section" aria-labelledby="safe-navigation-history-heading">
            <div class="dashboard-section-heading">
                <div>
                    <p class="eyebrow">ADMINISTRATOR HISTORY</p>
                    <h2 id="safe-navigation-history-heading">Submitted URLs</h2>
                </div>
            </div>
            @if ($checks->isEmpty())
                <x-empty-state
                    title="No URLs recorded"
                    description="Submitted URLs and their provider-status results will be listed here. No URL will be opened automatically."
                />
            @else
                <div class="scanner-history-list">
                    @foreach ($checks as $check)
                        <a class="scanner-history-row safe-navigation-history-row" href="{{ route('safe-navigation.checks.show', $check) }}">
                            <span>
                                <strong><code>{{ $check->url }}</code></strong>
                                <small>Submitted {{ $check->assessed_at->format('M j, Y H:i') }}</small>
                            </span>
                            <x-status-badge tone="muted">{{ str($check->display_classification->value)->headline() }}</x-status-badge>
                            <span class="scanner-history-open">View result <span aria-hidden="true">→</span></span>
                        </a>
                    @endforeach
                </div>
                <div class="scanner-pagination">{{ $checks->links() }}</div>
            @endif
        </section>
    </div>
@endsection
