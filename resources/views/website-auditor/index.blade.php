@extends('layouts.app')

@section('title', 'Website auditor')

@section('workspace')
    <div class="workspace-content website-auditor-content">
        <header class="workspace-heading">
            <div>
                <p class="eyebrow">WORKSPACE <span aria-hidden="true">/</span> WEBSITE AUDITOR</p>
                <h1>Website auditor</h1>
                <p class="workspace-intro">Review a bounded set of passive website configuration signals. Audits use only hostnames you registered and verified in the scanner.</p>
            </div>
        </header>

        @if (session('status'))
            <div class="form-notice auditor-notice" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="form-error auditor-error" role="alert">
                <strong>We could not start that assessment.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-panel class="auditor-scope-panel">
            <div>
                <p class="eyebrow">DIRECT PUBLIC URL AUDIT</p>
                <h2>Analyse a public website URL</h2>
                <p>No DNS ownership registration is required for the direct passive URL check. Private and reserved addresses are blocked before network access.</p>
            </div>
            <form method="POST" action="{{ route('website-auditor.url.store') }}" class="auditor-site-row">
                @csrf
                <div>
                    <label for="auditor-url">Public website URL</label>
                    <input id="auditor-url" name="url" type="url" value="{{ old('url') }}" maxlength="2048" placeholder="https://example.com" required>
                </div>
                <label>
                    <input type="checkbox" name="authorization_confirmed" value="1" required>
                    I am authorized to assess this public website
                </label>
                <button class="button button-primary button-small" type="submit">Start direct audit</button>
            </form>
            @if (session('direct_audit_result'))
                @php($audit = session('direct_audit_result'))
                <div class="form-notice" role="status">
                    <strong>{{ count($audit['findings']) }} finding(s) observed</strong>
                    <p><code>{{ $audit['url'] }}</code></p>
                    <ul>
                        @foreach ($audit['checks'] as $check)
                            <li>{{ $check['check_key'] }}: {{ $check['outcome'] }} — {{ $check['summary'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-panel>

        <x-panel class="auditor-scope-panel">
            <div>
                <p class="eyebrow">AUTHORIZED SCOPE</p>
                <h2>Select a verified hostname</h2>
                <p>Only your DNS-verified assets are available. Confirm authorization for each assessment request. The auditor will request the root page only and will not follow redirects or fetch embedded resources.</p>
            </div>
            @if ($assets->isEmpty())
                <x-empty-state
                    title="No verified websites available"
                    description="Register and verify a hostname you are authorized to assess before starting a website audit."
                >
                    <x-slot:action>
                        <a class="button button-outline button-small" href="{{ route('scanner.assets.index') }}">Register a hostname</a>
                    </x-slot:action>
                </x-empty-state>
            @else
                <div class="auditor-site-list">
                    @foreach ($assets as $asset)
                        <form class="auditor-site-row" method="POST" action="{{ route('website-auditor.assets.assess', $asset) }}">
                            @csrf
                            <div>
                                <strong>{{ $asset->hostname }}</strong>
                                <small>DNS ownership verified {{ $asset->verified_at?->format('M j, Y') }}</small>
                            </div>
                            <label>
                                <input type="checkbox" name="authorization_confirmed" value="1" required>
                                I am authorized to assess this website
                            </label>
                            <button class="button button-primary button-small" type="submit">Start website audit</button>
                        </form>
                    @endforeach
                </div>
            @endif
        </x-panel>

        <x-panel class="auditor-limit-panel">
            <span class="safety-icon" aria-hidden="true">
                <svg viewBox="0 0 20 20"><path d="M10 2.5 16 5v4.7c0 3.5-2.5 6.1-6 7.8-3.5-1.7-6-4.3-6-7.8V5l6-2.5Z" /><path d="m7.5 9.8 1.7 1.7 3.5-3.7" /></svg>
            </span>
            <div>
                <strong>Passive checks with explicit limits</strong>
                <p>One verified hostname, ports 80/443, DNS-pinned requests, verified TLS, short timeouts, a 256 KiB HTML inspection limit, and no redirect following or resource crawling.</p>
            </div>
        </x-panel>

        <section class="scanner-section" aria-labelledby="audit-history-heading">
            <div class="dashboard-section-heading">
                <div>
                    <p class="eyebrow">ACCOUNT HISTORY</p>
                    <h2 id="audit-history-heading">Website assessments</h2>
                </div>
            </div>
            @if ($audits->isEmpty())
                <x-empty-state
                    title="No website audits yet"
                    description="Completed, queued, and failed website audits will appear here with their own evidence-based reports."
                />
            @else
                <div class="scanner-history-list">
                    @foreach ($audits as $audit)
                        <a class="scanner-history-row" href="{{ route('website-auditor.runs.show', $audit) }}">
                            <span>
                                <strong>{{ $audit->securityAsset->hostname }}</strong>
                                <small>Requested {{ $audit->requested_at->format('M j, Y H:i') }}</small>
                            </span>
                            <x-status-badge tone="muted">{{ str($audit->status->value)->headline() }}</x-status-badge>
                            <span class="scanner-history-open">Open report <span aria-hidden="true">→</span></span>
                        </a>
                    @endforeach
                </div>
                <div class="scanner-pagination">{{ $audits->links() }}</div>
            @endif
        </section>
    </div>
@endsection
