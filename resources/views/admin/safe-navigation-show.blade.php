@extends('layouts.app')

@section('title', 'Safe Navigation result')

@section('workspace')
    @php
        $statusCopy = match ($classification) {
            \App\Enums\UrlThreatClassification::Malicious => 'A reputation source reported a known dangerous URL. Do not visit it.',
            \App\Enums\UrlThreatClassification::Suspicious => 'A reputation source reported a suspicious signal. Treat the destination cautiously and do not open it from this page.',
            \App\Enums\UrlThreatClassification::NoThreatDetected => 'No threat was detected by the available sources. This is not proof that the URL is safe.',
            \App\Enums\UrlThreatClassification::UnableToVerify => 'One or more available providers could not verify this URL. Do not interpret the missing result as safe.',
            \App\Enums\UrlThreatClassification::IntegrationUnavailable => 'No reputation provider is connected. This URL was not checked and its risk is unknown.',
        };
        $statusTone = match ($classification) {
            \App\Enums\UrlThreatClassification::Malicious,
            \App\Enums\UrlThreatClassification::Suspicious => 'warning',
            \App\Enums\UrlThreatClassification::NoThreatDetected => 'good',
            default => 'muted',
        };
    @endphp

    <div class="workspace-content safe-navigation-content">
        <header class="workspace-heading">
            <div>
                <p class="eyebrow">ADMINISTRATION / SAFE NAVIGATION</p>
                <h1>URL assessment</h1>
                <p class="workspace-intro">This record belongs to the administrator who submitted it. No destination is linked or opened.</p>
            </div>
            <div class="workspace-actions">
                <a class="button button-outline button-small" href="{{ route('safe-navigation.index') }}">Back to Safe Navigation</a>
            </div>
        </header>

        <x-panel class="safe-navigation-result-panel">
            <div class="safe-navigation-result-heading">
                <div>
                    <p class="eyebrow">THREAT CLASSIFICATION</p>
                    <h2>{{ str($classification->value)->headline() }}</h2>
                </div>
                <x-status-badge :tone="$statusTone">{{ str($classification->value)->headline() }}</x-status-badge>
            </div>
            <p>{{ $statusCopy }}</p>
            <dl class="safe-navigation-url-detail">
                <div><dt>Normalized URL</dt><dd><code>{{ $check->url }}</code></dd></div>
                <div><dt>Recorded</dt><dd>{{ $check->assessed_at->format('M j, Y H:i:s') }} UTC</dd></div>
            </dl>
        </x-panel>

        <section class="scanner-section" aria-labelledby="provider-results-heading">
            <div class="dashboard-section-heading">
                <div>
                    <p class="eyebrow">EVIDENCE</p>
                    <h2 id="provider-results-heading">Provider results</h2>
                </div>
            </div>
            <div class="safe-navigation-provider-grid">
                @foreach ($providerResults as $result)
                    <x-panel class="safe-navigation-provider-card">
                        <div>
                            <strong>{{ $result['provider_name'] }}</strong>
                            <x-status-badge tone="muted">{{ str($result['classification'])->headline() }}</x-status-badge>
                        </div>
                        <p>{{ $result['evidence'] ?? 'No additional provider evidence was recorded.' }}</p>
                        @if (! empty($result['threat_types']))
                            <small>Threat types: {{ implode(', ', $result['threat_types']) }}</small>
                        @endif
                    </x-panel>
                @endforeach
            </div>
        </section>

        <x-panel class="safe-navigation-warning-panel safe-navigation-result-warning">
            <span class="safety-icon" aria-hidden="true">
                <svg viewBox="0 0 20 20"><path d="M10 2.5 16 5v4.7c0 3.5-2.5 6.1-6 7.8-3.5-1.7-6-4.3-6-7.8V5l6-2.5Z" /><path d="M10 7v3.5m0 2.5h.01" /></svg>
            </span>
            <div>
                <strong>Do not rely on reputation results as a guarantee</strong>
                <p>Threat lists can be incomplete or contain false positives. Do not automatically open or redirect to this URL.</p>
            </div>
        </x-panel>
    </div>
@endsection
