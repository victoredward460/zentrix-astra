@extends('layouts.app')

@section('title', 'Security dashboard')

@section('workspace')
    <div class="workspace-content dashboard-content">
        <header class="workspace-heading dashboard-heading">
            <div>
                <p class="eyebrow">WORKSPACE <span aria-hidden="true">/</span> SECURITY OVERVIEW</p>
                <h1>Security overview</h1>
                <p class="workspace-intro">Welcome, {{ $user->name }}. This dashboard only displays information available to your account.</p>
            </div>
            <div class="workspace-actions">
                <a class="button button-outline button-small" href="{{ route('profile.edit') }}">Account profile</a>
                @if ($platformFeatures['scanner']['enabled'] || $user->isAdministrator())
                    <a class="button button-primary button-small" href="{{ route('scanner.assets.index') }}">
                        Open scanner
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4" /></svg>
                    </a>
                @endif
            </div>
        </header>

        <x-panel class="readiness-panel dashboard-system-panel">
            <div class="readiness-copy">
                <span class="readiness-symbol" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M4 5h16v14H4z" /><path d="M8 9h8M8 13h5" /></svg>
                </span>
                <div>
                    <h2>Database connectivity</h2>
                    <p>Platform readiness only; this does not represent a security assessment.</p>
                </div>
            </div>
            <div class="readiness-result">
                <x-loading-state id="database-health-loading" label="Checking connection" />
                <span class="health-result" id="database-health-ready" hidden>
                    <span class="health-dot"></span>
                    <span>Connected</span>
                </span>
                <x-error-state
                    id="database-health-failed"
                    title="Connection unavailable"
                    description="The platform could not confirm database readiness."
                    hidden
                />
                <span class="sr-only" id="database-health-announcement" aria-live="polite"></span>
            </div>
        </x-panel>

        <x-panel class="dashboard-posture-panel">
            <div class="dashboard-posture-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 3 20 6v6c0 4.2-3.2 7.3-8 9-4.8-1.7-8-4.8-8-9V6l8-3Z" /><path d="M12 8v4m0 3h.01" /></svg>
            </div>
            <div class="dashboard-posture-copy">
                <p class="eyebrow">SECURITY STATUS</p>
                <h2>No assessment data yet</h2>
                <p>No security posture or score is reported because no assessments have produced records for this account. This is not a clean bill of health.</p>
            </div>
            <x-status-badge tone="muted">Not assessed</x-status-badge>
        </x-panel>

        <section class="dashboard-section" aria-labelledby="activity-summary-heading">
            <div class="dashboard-section-heading">
                <div>
                    <p class="eyebrow">ACTIVITY</p>
                    <h2 id="activity-summary-heading">Assessment summary</h2>
                </div>
                @if ($dashboard->metrics === null)
                    <x-status-badge tone="muted">No assessment records</x-status-badge>
                @endif
            </div>
            <div class="dashboard-metrics-grid">
                @php
                    $summaryMetrics = [
                        ['label' => 'Completed scans', 'value' => $dashboard->metrics?->completedScans, 'note' => 'Scanner records only'],
                        ['label' => 'Active assessments', 'value' => $dashboard->metrics?->activeAssessments, 'note' => 'Currently running'],
                        ['label' => 'Open findings', 'value' => $dashboard->metrics?->openFindings, 'note' => 'Requires review'],
                        ['label' => 'File checks', 'value' => $dashboard->metrics?->fileChecks, 'note' => 'Private metadata/hash records'],
                    ];
                @endphp
                @foreach ($summaryMetrics as $metric)
                    <x-panel class="dashboard-metric-card">
                        <span class="dashboard-metric-label">{{ $metric['label'] }}</span>
                        <strong class="dashboard-metric-value">
                            {{ $metric['value'] === null ? '—' : number_format($metric['value']) }}
                        </strong>
                        <span class="dashboard-metric-note">
                            {{ $metric['value'] === null ? 'Not available yet' : $metric['note'] }}
                        </span>
                    </x-panel>
                @endforeach
            </div>
        </section>

        <div class="dashboard-panels-grid">
            <x-panel class="dashboard-data-panel" aria-labelledby="findings-heading">
                <div class="dashboard-panel-heading">
                    <div>
                        <p class="eyebrow">REVIEW</p>
                        <h2 id="findings-heading">Recent security findings</h2>
                    </div>
                    <x-status-badge tone="muted">
                        {{ $dashboard->metrics === null && $dashboard->recentFindings->isEmpty() ? 'Unavailable' : $dashboard->recentFindings->count().' records' }}
                    </x-status-badge>
                </div>
                @if ($dashboard->recentFindings->isEmpty())
                    <x-empty-state
                        title="No findings to review"
                        description="Findings will appear here only after an authorized assessment records evidence. No findings have been recorded for your account."
                    />
                @else
                    <div class="dashboard-record-list">
                        @foreach ($dashboard->recentFindings as $finding)
                            <article class="dashboard-finding-row">
                                <div>
                                    <h3>{{ $finding->title }}</h3>
                                    <p>{{ $finding->verificationState }} · {{ $finding->recordedAt->format('M j, Y H:i') }}</p>
                                </div>
                                <x-status-badge tone="muted">{{ $finding->severity }}</x-status-badge>
                            </article>
                        @endforeach
                    </div>
                @endif
            </x-panel>

            <x-panel class="dashboard-data-panel dashboard-trend-panel" aria-labelledby="trend-heading">
                <div class="dashboard-panel-heading">
                    <div>
                        <p class="eyebrow">TRENDS</p>
                        <h2 id="trend-heading">Security activity</h2>
                    </div>
                    <x-status-badge tone="muted">No trend data</x-status-badge>
                </div>
                <x-empty-state
                    title="A chart would be misleading right now."
                    description="There are no dated scan records to visualize. Activity charts will be shown when real, account-scoped history is available."
                />
            </x-panel>
        </div>

        <div class="dashboard-panels-grid dashboard-history-grid">
            <x-panel class="dashboard-data-panel" aria-labelledby="scan-history-heading">
                <div class="dashboard-panel-heading">
                    <div>
                        <p class="eyebrow">HISTORY</p>
                        <h2 id="scan-history-heading">Scan history</h2>
                    </div>
                    <x-status-badge tone="muted">
                        {{ $dashboard->metrics === null && $dashboard->scanHistory->isEmpty() ? 'Unavailable' : $dashboard->scanHistory->count().' records' }}
                    </x-status-badge>
                </div>
                @if ($dashboard->scanHistory->isEmpty())
                    <x-empty-state
                        title="Your scan history is empty"
                        description="No scans have been recorded for your account. Register and verify a hostname you are authorized to assess in the scanner."
                    />
                @else
                    <div class="dashboard-table-scroll">
                        <table class="dashboard-table">
                            <caption class="sr-only">Recent account-owned security scans</caption>
                            <thead>
                                <tr><th scope="col">Module / target</th><th scope="col">Status</th><th scope="col">Started</th><th scope="col">Findings</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($dashboard->scanHistory as $scan)
                                    <tr>
                                        <td><strong>{{ $scan->module }}</strong><span>{{ $scan->target }}</span></td>
                                        <td>{{ $scan->status }}</td>
                                        <td><time datetime="{{ $scan->startedAt->toIso8601String() }}">{{ $scan->startedAt->format('M j, Y H:i') }}</time></td>
                                        <td>{{ number_format($scan->findingCount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-panel>

            <x-panel class="dashboard-data-panel" aria-labelledby="timeline-heading">
                <div class="dashboard-panel-heading">
                    <div>
                        <p class="eyebrow">TIMELINE</p>
                        <h2 id="timeline-heading">Recent activity</h2>
                    </div>
                </div>
                @if ($dashboard->activityTimeline->isEmpty())
                    <x-empty-state
                        title="No security activity recorded"
                        description="Security events will be listed here when supported modules write real activity records."
                    />
                @else
                    <ol class="dashboard-timeline">
                        @foreach ($dashboard->activityTimeline as $activity)
                            <li>
                                <span class="dashboard-timeline-marker" aria-hidden="true"></span>
                                <div>
                                    <h3>{{ $activity->title }}</h3>
                                    <p>{{ $activity->description }}</p>
                                    <time datetime="{{ $activity->occurredAt->toIso8601String() }}">{{ $activity->occurredAt->format('M j, Y H:i') }}</time>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-panel>
        </div>

        <section class="dashboard-section" id="module-availability" aria-labelledby="quick-access-heading">
            <div class="dashboard-section-heading">
                <div>
                    <p class="eyebrow">QUICK ACCESS</p>
                    <h2 id="quick-access-heading">Available workspace links</h2>
                </div>
                <a class="text-link" href="{{ route('home') }}#modules">
                    View module roadmap
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11m-4-4 4 4-4 4" /></svg>
                </a>
            </div>
            <div class="dashboard-quick-grid">
                @if ($platformFeatures['scanner']['enabled'] || $user->isAdministrator())
                    <a class="dashboard-quick-card" href="{{ route('scanner.assets.index') }}">
                        <span class="dashboard-quick-mark" aria-hidden="true">01</span>
                        <span><strong>Security scanner</strong><small>Verify an authorized hostname</small></span>
                        <span class="dashboard-quick-arrow" aria-hidden="true">→</span>
                    </a>
                @endif
                @if ($platformFeatures['website-auditor']['enabled'] || $user->isAdministrator())
                    <a class="dashboard-quick-card" href="{{ route('website-auditor.index') }}">
                        <span class="dashboard-quick-mark" aria-hidden="true">02</span>
                        <span><strong>Website auditor</strong><small>Review a verified website</small></span>
                        <span class="dashboard-quick-arrow" aria-hidden="true">→</span>
                    </a>
                @endif
                @if ($platformFeatures['file-check']['enabled'] || $user->isAdministrator())
                    <a class="dashboard-quick-card" href="{{ route('file-check.index') }}">
                        <span class="dashboard-quick-mark" aria-hidden="true">03</span>
                        <span><strong>File Check</strong><small>Record private file metadata</small></span>
                        <span class="dashboard-quick-arrow" aria-hidden="true">→</span>
                    </a>
                @endif
                <a class="dashboard-quick-card" href="{{ route('profile.edit') }}">
                    <span class="dashboard-quick-mark" aria-hidden="true">04</span>
                    <span><strong>Account profile</strong><small>Manage your account details</small></span>
                    <span class="dashboard-quick-arrow" aria-hidden="true">→</span>
                </a>
                @if ($platformFeatures['academy']['enabled'] || $user->isAdministrator())
                    <a class="dashboard-quick-card" href="{{ route('academy.index') }}">
                        <span class="dashboard-quick-mark" aria-hidden="true">05</span>
                        <span><strong>Cybersecurity Academy</strong><small>Learn and track your progress</small></span>
                        <span class="dashboard-quick-arrow" aria-hidden="true">→</span>
                    </a>
                @endif
                <a class="dashboard-quick-card" href="{{ route('home') }}#modules">
                    <span class="dashboard-quick-mark" aria-hidden="true">06</span>
                    <span><strong>Module roadmap</strong><small>Review planned capabilities</small></span>
                    <span class="dashboard-quick-arrow" aria-hidden="true">→</span>
                </a>
                @if ($user->isAdministrator())
                    <a class="dashboard-quick-card" href="{{ route('safe-navigation.index') }}">
                        <span class="dashboard-quick-mark" aria-hidden="true">08</span>
                        <span><strong>Safe Navigation</strong><small>Administrator URL reputation workspace</small></span>
                        <span class="dashboard-quick-arrow" aria-hidden="true">→</span>
                    </a>
                    <a class="dashboard-quick-card" href="{{ route('admin.users.index') }}">
                        <span class="dashboard-quick-mark" aria-hidden="true">07</span>
                        <span><strong>User management</strong><small>Administrator access</small></span>
                        <span class="dashboard-quick-arrow" aria-hidden="true">→</span>
                    </a>
                    <a class="dashboard-quick-card" href="{{ route('admin.dashboard') }}">
                        <span class="dashboard-quick-mark" aria-hidden="true">09</span>
                        <span><strong>Platform administration</strong><small>Operational dashboard and activity</small></span>
                        <span class="dashboard-quick-arrow" aria-hidden="true">→</span>
                    </a>
                @endif
            </div>
            @if ($dashboard->accountCreatedAt)
                <p class="dashboard-account-note">Workspace member since {{ $dashboard->accountCreatedAt->format('F Y') }}.</p>
            @endif
        </section>
    </div>
@endsection
