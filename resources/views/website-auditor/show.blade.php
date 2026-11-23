@extends('layouts.app')

@section('title', 'Website audit report')

@section('workspace')
    <div class="workspace-content website-auditor-content">
        <div class="auditor-report-actions">
            <a class="button button-outline button-small no-print" href="{{ route('website-auditor.index') }}">Back to website auditor</a>
            <button class="button button-primary button-small no-print" type="button" data-print-report>Print / Save PDF</button>
        </div>

        <article class="website-audit-report" id="website-audit-report">
            <header class="workspace-heading">
                <div>
                    <p class="eyebrow">ZENTRIX ASTRA <span aria-hidden="true">/</span> WEBSITE AUDIT REPORT</p>
                    <h1>{{ $scanRun->securityAsset->hostname }}</h1>
                    <p class="workspace-intro">Requested {{ $scanRun->requested_at->format('M j, Y H:i') }}
                        @if ($scanRun->finished_at)
                            · Finished {{ $scanRun->finished_at->format('M j, Y H:i') }}
                        @endif
                    </p>
                </div>
                <x-status-badge :tone="$scanRun->status->value === 'completed' ? 'good' : 'warning'">
                    {{ str($scanRun->status->value)->headline() }}
                </x-status-badge>
            </header>

            @if (in_array($scanRun->status->value, ['requested', 'queued', 'processing'], true))
                <x-panel class="scanner-progress no-print" role="status">
                    <x-loading-state id="website-audit-loading" label="Assessment is {{ $scanRun->status->value }}. Refresh this page for the latest report." />
                    <a class="button button-outline button-small" href="{{ route('website-auditor.runs.show', $scanRun) }}">Refresh</a>
                </x-panel>
            @elseif ($scanRun->status->value === 'failed')
                <x-error-state
                    title="The assessment is incomplete"
                    description="One or more requests failed or were blocked by target-safety validation. The report does not imply a clean result."
                />
            @endif

            <x-panel class="auditor-risk-panel">
                <div>
                    <p class="eyebrow">OBSERVED RISK CATEGORY</p>
                    <h2>{{ $report->riskCategory }}</h2>
                    <p>{{ $report->scopeNote }}</p>
                    @if ($report->incompleteCheckCount > 0)
                        <p>{{ $report->incompleteCheckCount }} check(s) were blocked, failed, or inconclusive and must not be interpreted as clean.</p>
                    @endif
                </div>
                <div class="auditor-severity-counts" aria-label="Finding counts by severity">
                    @foreach ($report->severityCounts as $severity => $count)
                        <span><strong>{{ number_format($count) }}</strong> {{ str($severity)->headline() }}</span>
                    @endforeach
                </div>
            </x-panel>

            @if ($scanRun->checkResults->isNotEmpty())
                <section class="scanner-section" aria-labelledby="audit-checks-heading">
                    <div class="dashboard-section-heading">
                        <div>
                            <p class="eyebrow">OBSERVATIONS</p>
                            <h2 id="audit-checks-heading">Website information and checks</h2>
                        </div>
                    </div>
                    <div class="scanner-check-list">
                        @foreach ($scanRun->checkResults as $result)
                            @php
                                $tone = match ($result->outcome->value) {
                                    'passed' => 'good',
                                    'finding', 'blocked', 'error' => 'warning',
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

            <section class="scanner-section" aria-labelledby="audit-findings-heading">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="eyebrow">FINDINGS AND RECOMMENDATIONS</p>
                        <h2 id="audit-findings-heading">Review items</h2>
                    </div>
                </div>
                @if ($scanRun->findings->isEmpty())
                    <x-empty-state
                        title="No findings were recorded"
                        description="No finding was produced by the checks that completed. This limited assessment cannot establish that the website is completely secure."
                    />
                @else
                    <div class="scanner-check-list">
                        @foreach ($scanRun->findings as $finding)
                            <x-panel class="scanner-finding-card auditor-finding-card">
                                <div class="scanner-asset-heading">
                                    <div>
                                        <p class="eyebrow">{{ str($finding->severity->value)->headline() }} · {{ str($finding->verification_state->value)->headline() }}</p>
                                        <h3>{{ $finding->title }}</h3>
                                        <p>{{ $finding->description }}</p>
                                    </div>
                                </div>
                                <div class="auditor-recommendation">
                                    <strong>Recommended corrective action</strong>
                                    <p>{{ $finding->recommended_action }}</p>
                                </div>
                                <details class="scanner-evidence">
                                    <summary>View supporting evidence</summary>
                                    <pre>{{ json_encode($finding->evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            </x-panel>
                        @endforeach
                    </div>
                @endif
            </section>

            <footer class="auditor-report-footer">
                Limited passive observations only. No vulnerability exploitation, authenticated testing, crawling, or comprehensive security certification was performed.
            </footer>
        </article>
    </div>
@endsection
