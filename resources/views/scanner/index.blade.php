@extends('layouts.app')

@section('title', 'Security scanner')

@section('workspace')
    <div class="workspace-content scanner-content">
        <header class="workspace-heading">
            <div>
                <p class="eyebrow">WORKSPACE <span aria-hidden="true">/</span> PASSIVE ASSESSMENT</p>
                <h1>Security scanner</h1>
                <p class="workspace-intro">Assess only a hostname you own or are explicitly authorized to test. Registration requires DNS control before any network request is made.</p>
            </div>
        </header>

        @if (session('status'))
            <div class="form-notice scanner-notice" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="form-error scanner-error" role="alert">
                <strong>We could not complete that action.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('ownership_challenge'))
            <x-panel class="scanner-challenge-panel" aria-labelledby="ownership-challenge-heading">
                <p class="eyebrow">DNS OWNERSHIP PROOF</p>
                <h2 id="ownership-challenge-heading">Add this TXT record</h2>
                <p>Publish the record at your DNS provider, then use Verify ownership beside the hostname. The challenge expires after 24 hours.</p>
                <dl class="scanner-challenge-values">
                    <div><dt>Hostname</dt><dd><code>{{ session('ownership_challenge.hostname') }}</code></dd></div>
                    <div><dt>Record name</dt><dd><code>{{ session('ownership_challenge.record_name') }}</code></dd></div>
                    <div><dt>Record value</dt><dd><code>{{ session('ownership_challenge.record_value') }}</code></dd></div>
                </dl>
                <p class="form-hint">The value is shown only in this response. If it is lost, generate a fresh challenge.</p>
            </x-panel>
        @endif

        <div class="scanner-panels">
            <x-panel class="scanner-register-panel">
                <p class="eyebrow">VIRUSTOTAL · URL REPUTATION</p>
                <h2>Analyse a website URL</h2>
                <p>Submit a public HTTP or HTTPS URL for VirusTotal analysis. Private addresses and credentials are rejected.</p>
                <form method="POST" action="{{ route('scanner.url.store') }}" class="scanner-form">
                    @csrf
                    <label for="scan-url">Website URL</label>
                    <input id="scan-url" name="url" type="url" value="{{ old('url') }}" maxlength="2048" placeholder="https://example.com" required>
                    <label class="scanner-attestation">
                        <input type="checkbox" name="authorization_confirmed" value="1" @checked(old('authorization_confirmed')) required>
                        <span>I am authorized to submit this public website URL for reputation analysis.</span>
                    </label>
                    <button class="button button-primary button-small" type="submit">Analyse with VirusTotal</button>
                </form>
                @if (session('url_scan_result'))
                    @php($urlResult = session('url_scan_result'))
                    <div class="form-notice" role="status">
                        <strong>{{ str($urlResult['classification'])->replace('_', ' ')->headline() }}</strong>
                        <p>{{ $urlResult['evidence'] }}</p>
                        <p><code>{{ $urlResult['url'] }}</code></p>
                    </div>
                @endif
            </x-panel>

            <x-panel class="scanner-register-panel">
                <p class="eyebrow">REGISTER AN ASSET</p>
                <h2>Start with a hostname</h2>
                <p>Enter a fully qualified DNS name, not a URL or IP address. We check DNS ownership before scanning.</p>
                <form method="POST" action="{{ route('scanner.assets.store') }}" class="scanner-form">
                    @csrf
                    <label for="hostname">Hostname</label>
                    <input id="hostname" name="hostname" type="text" value="{{ old('hostname') }}"
                        autocomplete="url" placeholder="example.com" maxlength="253" required>
                    <label class="scanner-attestation">
                        <input type="checkbox" name="authorization_confirmed" value="1" @checked(old('authorization_confirmed')) required>
                        <span>I own this hostname or have explicit authorization to assess it.</span>
                    </label>
                    <button class="button button-primary button-small" type="submit">Register hostname</button>
                </form>
            </x-panel>

            <x-panel class="scanner-safety-panel">
                <p class="eyebrow">SAFE SCOPE</p>
                <h2>Passive checks only</h2>
                <ul>
                    <li>DNS ownership TXT challenge before requesting a scan.</li>
                    <li>Only ports 80 and 443; HTTP HEAD requests, no response bodies.</li>
                    <li>Private, reserved, loopback and metadata IP ranges are blocked.</li>
                    <li>Requests are pinned to a validated public address; redirects are never followed.</li>
                    <li>TLS certificate verification remains enabled.</li>
                </ul>
            </x-panel>
        </div>

        <section class="scanner-section" aria-labelledby="registered-assets-heading">
            <div class="dashboard-section-heading">
                <div>
                    <p class="eyebrow">AUTHORIZED SCOPE</p>
                    <h2 id="registered-assets-heading">Registered hostnames</h2>
                </div>
            </div>

            @if ($assets->isEmpty())
                <x-empty-state title="No hostnames registered" description="Register a hostname you are authorized to assess. A DNS TXT challenge must be verified before scanning is enabled." />
            @else
                <div class="scanner-asset-list">
                    @foreach ($assets as $asset)
                        <x-panel class="scanner-asset-card">
                            <div class="scanner-asset-heading">
                                <div>
                                    <h3>{{ $asset->hostname }}</h3>
                                    <p>Added {{ $asset->created_at->format('M j, Y') }}</p>
                                </div>
                                <x-status-badge :tone="$asset->verification_status->value === 'verified' ? 'good' : 'warning'">
                                    {{ ucfirst($asset->verification_status->value) }}
                                </x-status-badge>
                            </div>
                            @if ($asset->verification_status->value === 'verified')
                                <form method="POST" action="{{ route('scanner.assets.scans.store', $asset) }}">
                                    @csrf
                                    <button class="button button-primary button-small" type="submit">Request passive scan</button>
                                </form>
                            @else
                                <div class="scanner-asset-actions">
                                    <form method="POST" action="{{ route('scanner.assets.verify', $asset) }}">
                                        @csrf
                                        <button class="button button-primary button-small" type="submit">Verify ownership</button>
                                    </form>
                                    <form method="POST" action="{{ route('scanner.assets.challenge', $asset) }}">
                                        @csrf
                                        <button class="button button-outline button-small" type="submit">Generate fresh TXT challenge</button>
                                    </form>
                                </div>
                            @endif
                        </x-panel>
                    @endforeach
                </div>
                <div class="scanner-pagination">{{ $assets->links() }}</div>
            @endif
        </section>

        <section class="scanner-section" aria-labelledby="scan-history-heading">
            <div class="dashboard-section-heading">
                <div>
                    <p class="eyebrow">ASSESSMENT RECORDS</p>
                    <h2 id="scan-history-heading">Scan history</h2>
                </div>
            </div>
            @if ($scanRuns->isEmpty())
                <x-empty-state title="No scan runs yet" description="After a verified hostname is scanned, its real lifecycle and results will appear here." />
            @else
                <div class="scanner-history-list">
                    @foreach ($scanRuns as $scanRun)
                        <a class="scanner-history-row" href="{{ route('scanner.runs.show', $scanRun) }}">
                            <span><strong>{{ $scanRun->securityAsset->hostname }}</strong><small>Requested {{ $scanRun->requested_at->format('M j, Y H:i') }}</small></span>
                            <x-status-badge tone="muted">{{ ucfirst($scanRun->status->value) }}</x-status-badge>
                            <span class="scanner-history-open">View result <span aria-hidden="true">→</span></span>
                        </a>
                    @endforeach
                </div>
                <div class="scanner-pagination">{{ $scanRuns->links() }}</div>
            @endif
        </section>
    </div>
@endsection
