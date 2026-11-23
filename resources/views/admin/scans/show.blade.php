@extends('layouts.app')

@section('title', 'Scan report')

@section('workspace')
    <div class="workspace-content scanner-content">
        <header class="workspace-heading">
            <div><p class="eyebrow">ADMINISTRATION / SCAN REPORT</p><h1>{{ $scanRun->scan_type === \App\Enums\ScanType::WebsiteAudit ? 'Website audit' : 'Security scan' }}</h1><p class="workspace-intro">Owner: {{ $scanRun->user?->name ?? 'Deleted account' }} ({{ $scanRun->user?->email ?? 'unavailable' }})</p></div>
            <a class="button button-outline button-small" href="{{ route('admin.scans.index') }}">Back to scan activity</a>
        </header>
        <x-panel class="module-table-panel">
            <dl class="safe-navigation-url-detail">
                <div><dt>Target</dt><dd>{{ $scanRun->securityAsset?->hostname ?? 'Unavailable' }}</dd></div>
                <div><dt>Status</dt><dd>{{ str($scanRun->status->value)->title() }}</dd></div>
                <div><dt>Requested</dt><dd>{{ $scanRun->requested_at->format('M j, Y H:i:s') }}</dd></div>
                <div><dt>Started</dt><dd>{{ $scanRun->started_at?->format('M j, Y H:i:s') ?? 'Not started' }}</dd></div>
                <div><dt>Finished</dt><dd>{{ $scanRun->finished_at?->format('M j, Y H:i:s') ?? 'Not finished' }}</dd></div>
                @if ($scanRun->failure_code)
                    <div><dt>Failure code</dt><dd>{{ $scanRun->failure_code }}</dd></div>
                @endif
            </dl>
        </x-panel>

        <section class="dashboard-section" aria-labelledby="admin-check-results">
            <div class="dashboard-section-heading"><div><p class="eyebrow">OBSERVATIONS</p><h2 id="admin-check-results">Check results</h2></div></div>
            @forelse ($scanRun->checkResults as $result)
                <x-panel class="dashboard-data-panel">
                    <h3>{{ str($result->check_key)->replace('_', ' ')->title() }}</h3>
                    <p>{{ $result->summary }}</p>
                    <x-status-badge tone="muted">{{ str($result->outcome->value)->title() }}</x-status-badge>
                    @if ($result->evidence)
                        <details><summary>Evidence</summary><pre>{{ json_encode($result->evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
                    @endif
                </x-panel>
            @empty
                <x-panel><x-empty-state title="No check results stored" description="This run has no persisted check-result records." /></x-panel>
            @endforelse
        </section>

        <section class="dashboard-section" aria-labelledby="admin-findings">
            <div class="dashboard-section-heading"><div><p class="eyebrow">FINDINGS</p><h2 id="admin-findings">Recorded findings</h2></div></div>
            @forelse ($scanRun->findings as $finding)
                <x-panel class="dashboard-data-panel">
                    <h3>{{ $finding->title }}</h3>
                    <p>{{ $finding->description }}</p>
                    <p><strong>Recommended action:</strong> {{ $finding->recommended_action }}</p>
                    <x-status-badge tone="muted">{{ str($finding->severity->value)->title() }} · {{ str($finding->verification_state->value)->replace('_', ' ')->title() }}</x-status-badge>
                    @if ($finding->evidence)
                        <details><summary>Evidence</summary><pre>{{ json_encode($finding->evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
                    @endif
                </x-panel>
            @empty
                <x-panel><x-empty-state title="No findings recorded" description="No findings were stored for this scan. This alone is not a statement that the target is secure." /></x-panel>
            @endforelse
        </section>
    </div>
@endsection
