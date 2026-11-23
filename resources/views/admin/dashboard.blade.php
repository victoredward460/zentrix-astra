@extends('layouts.app')

@section('title', 'Platform administration')

@section('workspace')
    <div class="workspace-content dashboard-content">
        <header class="workspace-heading dashboard-heading">
            <div>
                <p class="eyebrow">ADMINISTRATION / PLATFORM OVERVIEW</p>
                <h1>Platform administration</h1>
                <p class="workspace-intro">Operational totals are calculated from persisted records. Private user conversations and uploaded file contents are not exposed here.</p>
            </div>
            <div class="workspace-actions">
                <a class="button button-outline button-small" href="{{ route('admin.features.index') }}">Platform controls</a>
                <a class="button button-outline button-small" href="{{ route('admin.configuration') }}">Integration status</a>
            </div>
        </header>

        @php
            $metricsList = [
                ['label' => 'User accounts', 'value' => $metrics['users'], 'note' => $metrics['active_users'].' active · '.$metrics['inactive_users'].' disabled'],
                ['label' => 'Scan runs', 'value' => $metrics['scans'], 'note' => $metrics['active_scans'].' requested, queued, or processing'],
                ['label' => 'Open findings', 'value' => $metrics['open_findings'], 'note' => 'Recorded evidence requiring review'],
                ['label' => 'File checks', 'value' => $metrics['file_checks'], 'note' => 'Metadata/hash records; not malware analysis'],
                ['label' => 'Safe Navigation checks', 'value' => $metrics['safe_navigation_checks'], 'note' => 'History count; provider lookups may be unavailable'],
                ['label' => 'AI conversations', 'value' => $metrics['agent_threads'], 'note' => 'Count only; conversation content remains private'],
                ['label' => 'Lessons completed', 'value' => $metrics['completed_lessons'], 'note' => 'Recorded learner completions'],
            ];
        @endphp
        <section class="dashboard-section" aria-labelledby="admin-metrics-heading">
            <div class="dashboard-section-heading">
                <div><p class="eyebrow">LIVE DATABASE TOTALS</p><h2 id="admin-metrics-heading">Platform activity</h2></div>
            </div>
            <div class="dashboard-metrics-grid">
                @foreach ($metricsList as $metric)
                    <x-panel class="dashboard-metric-card">
                        <span class="dashboard-metric-label">{{ $metric['label'] }}</span>
                        <strong class="dashboard-metric-value">{{ number_format($metric['value']) }}</strong>
                        <span class="dashboard-metric-note">{{ $metric['note'] }}</span>
                    </x-panel>
                @endforeach
            </div>
        </section>

        <div class="dashboard-panels-grid">
            <x-panel class="dashboard-data-panel">
                <div class="dashboard-panel-heading">
                    <div><p class="eyebrow">ASSESSMENTS</p><h2>Recent scan activity</h2></div>
                    <a class="text-link" href="{{ route('admin.scans.index') }}">All scans</a>
                </div>
                @if ($recentScans->isEmpty())
                    <x-empty-state title="No scans recorded" description="Scan runs will appear here after users complete authorized assessments." />
                @else
                    <div class="dashboard-table-scroll">
                        <table class="dashboard-table">
                            <caption class="sr-only">Recent scan runs and owners</caption>
                            <thead><tr><th scope="col">Owner / target</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Findings</th></tr></thead>
                            <tbody>
                                @foreach ($recentScans as $scan)
                                    <tr>
                                        <td><strong>{{ $scan->user?->name ?? 'Deleted account' }}</strong><span>{{ $scan->securityAsset?->hostname ?? 'Target unavailable' }}</span></td>
                                        <td>{{ str($scan->scan_type->value)->replace('_', ' ')->title() }}</td>
                                        <td>{{ str($scan->status->value)->title() }}</td>
                                        <td><a href="{{ route('admin.scans.show', $scan) }}">{{ number_format($scan->findings_count) }} · View</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-panel>

            <x-panel class="dashboard-data-panel">
                <div class="dashboard-panel-heading">
                    <div><p class="eyebrow">AUDIT TRAIL</p><h2>Recent platform events</h2></div>
                    <a class="text-link" href="{{ route('admin.activity.index') }}">Activity log</a>
                </div>
                @if ($recentEvents->isEmpty())
                    <x-empty-state title="No audit events recorded" description="Administrative and authentication events will appear here as they occur." />
                @else
                    <ol class="dashboard-timeline">
                        @foreach ($recentEvents as $event)
                            <li>
                                <span class="dashboard-timeline-marker" aria-hidden="true"></span>
                                <div>
                                    <h3>{{ str($event->event_key)->replace(['.', '_'], ' ')->title() }}</h3>
                                    <p>{{ $event->actor?->email ?? 'Account no longer available' }}@if($event->subject_type) · {{ $event->subject_type }} #{{ $event->subject_id }}@endif</p>
                                    <time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('M j, Y H:i') }}</time>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-panel>
        </div>

        <section class="dashboard-section" aria-labelledby="admin-quick-links-heading">
            <div class="dashboard-section-heading">
                <div><p class="eyebrow">CONTROL CENTRE</p><h2 id="admin-quick-links-heading">Administration tools</h2></div>
            </div>
            <div class="dashboard-quick-grid">
                <a class="dashboard-quick-card" href="{{ route('admin.users.index') }}"><span class="dashboard-quick-mark">01</span><span><strong>User management</strong><small>Roles and account status</small></span><span class="dashboard-quick-arrow" aria-hidden="true">→</span></a>
                <a class="dashboard-quick-card" href="{{ route('admin.scans.index') }}"><span class="dashboard-quick-mark">02</span><span><strong>Scan activity</strong><small>Owner-linked scan reports</small></span><span class="dashboard-quick-arrow" aria-hidden="true">→</span></a>
                <a class="dashboard-quick-card" href="{{ route('admin.file-checks.index') }}"><span class="dashboard-quick-mark">03</span><span><strong>File Check records</strong><small>Private metadata and hash reports</small></span><span class="dashboard-quick-arrow" aria-hidden="true">→</span></a>
                <a class="dashboard-quick-card" href="{{ route('admin.academy.progress') }}"><span class="dashboard-quick-mark">04</span><span><strong>Academy progress</strong><small>Learner completion and quiz history</small></span><span class="dashboard-quick-arrow" aria-hidden="true">→</span></a>
                <a class="dashboard-quick-card" href="{{ route('admin.activity.index') }}"><span class="dashboard-quick-mark">05</span><span><strong>Platform activity</strong><small>Minimized audit events</small></span><span class="dashboard-quick-arrow" aria-hidden="true">→</span></a>
                <a class="dashboard-quick-card" href="{{ route('admin.security-events.index') }}"><span class="dashboard-quick-mark">06</span><span><strong>Security events</strong><small>Authentication and privileged actions</small></span><span class="dashboard-quick-arrow" aria-hidden="true">→</span></a>
                <a class="dashboard-quick-card" href="{{ route('admin.academy.index') }}"><span class="dashboard-quick-mark">07</span><span><strong>Academy content</strong><small>Manage published learning material</small></span><span class="dashboard-quick-arrow" aria-hidden="true">→</span></a>
                <a class="dashboard-quick-card" href="{{ route('safe-navigation.index') }}"><span class="dashboard-quick-mark">08</span><span><strong>Safe Navigation</strong><small>Administrator-only URL assessments</small></span><span class="dashboard-quick-arrow" aria-hidden="true">→</span></a>
                <a class="dashboard-quick-card" href="{{ route('admin.features.index') }}"><span class="dashboard-quick-mark">09</span><span><strong>Platform controls</strong><small>Registration and user module access</small></span><span class="dashboard-quick-arrow" aria-hidden="true">→</span></a>
            </div>
        </section>
    </div>
@endsection
