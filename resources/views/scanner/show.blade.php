@extends('layouts.app')

@section('title', 'Scan result')

@section('workspace')
    <div class="workspace-content scanner-content">
        <header class="workspace-heading">
            <div>
                <p class="eyebrow">SECURITY SCANNER <span aria-hidden="true">/</span> RESULT</p>
                <h1>{{ $scanRun->securityAsset->hostname }}</h1>
                <p class="workspace-intro">Passive assessment requested {{ $scanRun->requested_at->format('M j, Y H:i') }}. Observations are limited to the authorized hostname.</p>
            </div>
            <div class="workspace-actions">
                <x-status-badge tone="muted">{{ ucfirst($scanRun->status->value) }}</x-status-badge>
                <a class="button button-outline button-small" href="{{ route('scanner.assets.index') }}">Back to scanner</a>
            </div>
        </header>

        @if (in_array($scanRun->status->value, ['requested', 'queued', 'processing'], true))
            <x-panel class="scanner-progress" role="status">
                <x-loading-state id="scan-run-loading" label="Scan is {{ $scanRun->status->value }}. Refresh this page to see updates." />
                <a class="button button-outline button-small" href="{{ route('scanner.runs.show', $scanRun) }}">Refresh results</a>
            </x-panel>
        @elseif ($scanRun->status->value === 'failed')
            <x-error-state
                title="The scan did not complete"
                description="The target may not have responded, DNS may have changed, or safe-target validation may have blocked the request. Review the check outcomes below; no clean result is implied."
            />
        @endif

        @if ($scanRun->checkResults->isNotEmpty())
            <section class="scanner-section" aria-labelledby="checks-heading">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="eyebrow">OBSERVED CHECKS</p>
                        <h2 id="checks-heading">Assessment results</h2>
                    </div>
                </div>
                <div class="scanner-check-list">
                    @foreach ($scanRun->checkResults as $result)
                        @php
                            $tone = match ($result->outcome->value) {
                                'passed' => 'good',
                                'finding', 'blocked' => 'warning',
                                'error' => 'warning',
                                default => 'muted',
                            };
                        @endphp
                        <x-panel class="scanner-check-card">
                            <div class="scanner-asset-heading">
                                <div>
                                    <h3>{{ str($result->check_key)->replace('_', ' ')->headline() }}</h3>
                                    <p>{{ $result->summary }}</p>
                                </div>
                                <x-status-badge :tone="$tone">{{ str($result->outcome->value)->headline() }}</x-status-badge>
                            </div>
                            @if ($result->evidence)
                                <details class="scanner-evidence">
                                    <summary>View recorded evidence</summary>
                                    <pre>{{ json_encode($result->evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            @endif
                        </x-panel>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="scanner-section" aria-labelledby="findings-heading">
            <div class="dashboard-section-heading">
                <div>
                    <p class="eyebrow">EVIDENCE-BASED</p>
                    <h2 id="findings-heading">Security findings</h2>
                </div>
            </div>
            @if ($scanRun->findings->isEmpty())
                <x-empty-state
                    title="No findings were recorded"
                    description="This means no finding was recorded by these limited passive checks. It is not a comprehensive assessment or a guarantee that the host is secure."
                />
            @else
                <div class="scanner-check-list">
                    @foreach ($scanRun->findings as $finding)
                        <x-panel class="scanner-finding-card">
                            <div class="scanner-asset-heading">
                                <div>
                                    <p class="eyebrow">{{ ucfirst($finding->severity->value) }} · {{ ucfirst($finding->verification_state->value) }}</p>
                                    <h3>{{ $finding->title }}</h3>
                                    <p>{{ $finding->description }}</p>
                                </div>
                            </div>
                            <details class="scanner-evidence">
                                <summary>View supporting observation</summary>
                                <pre>{{ json_encode($finding->evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        </x-panel>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
