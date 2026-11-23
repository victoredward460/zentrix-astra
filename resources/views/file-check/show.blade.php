@extends('layouts.app')

@section('title', 'File Check record')

@section('workspace')
    <div class="workspace-content file-check-content">
        <header class="workspace-heading">
            <div>
                <p class="eyebrow">WORKSPACE <span aria-hidden="true">/</span> FILE CHECK</p>
                <h1>File record</h1>
                <p class="workspace-intro">Private metadata captured for this account. The original file is not available for download.</p>
            </div>
            <div class="workspace-actions">
                <a class="button button-outline button-small" href="{{ route('file-check.index') }}">Back to File Check</a>
            </div>
        </header>

        @if ($errors->any())
            <div class="form-error file-check-error" role="alert">
                <strong>We could not complete that File Check request.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-panel class="file-check-result-panel">
            <div class="file-check-result-heading">
                <div>
                    <p class="eyebrow">PROCESSING STATUS</p>
                    <h2>{{ $fileCheck->original_name }}</h2>
                </div>
                <x-status-badge tone="{{ $fileCheck->status->value === 'completed' ? 'good' : ($fileCheck->status->value === 'failed' ? 'warning' : 'muted') }}">
                    {{ str($fileCheck->status->value)->headline() }}
                </x-status-badge>
            </div>
            @if ($fileCheck->status->value === 'completed')
                <p class="form-hint">Metadata and SHA-256 were recorded. No content scanning or safety verdict was produced.</p>
            @elseif ($fileCheck->status->value === 'failed')
                <p class="form-hint">Processing did not complete. The attempt is recorded, but no hash or safety verdict is available.</p>
            @else
                <p class="form-hint">Processing is in progress. Refresh this page to check the current status.</p>
            @endif
            @if (session('status'))
                <p class="form-hint" role="status">{{ session('status') }}</p>
            @endif
        </x-panel>

        <x-panel class="file-check-details-panel">
            <p class="eyebrow">OBSERVED METADATA</p>
            <dl class="file-check-detail-grid">
                <div><dt>Original file name</dt><dd>{{ $fileCheck->original_name }}</dd></div>
                <div><dt>Detected MIME type</dt><dd>{{ $fileCheck->mime_type }}</dd></div>
                <div><dt>File size</dt><dd>{{ number_format($fileCheck->size_bytes) }} bytes</dd></div>
                <div><dt>Recorded</dt><dd>{{ $fileCheck->created_at->format('M j, Y H:i:s') }} UTC</dd></div>
                <div><dt>SHA-256</dt><dd class="file-check-hash">{{ $fileCheck->sha256 ?? 'Unavailable' }}</dd></div>
                <div><dt>Retention until</dt><dd>{{ $retentionDate->format('M j, Y H:i:s') }} UTC</dd></div>
            </dl>
        </x-panel>

        <x-panel class="file-check-limits-panel">
            <span class="safety-icon" aria-hidden="true">
                <svg viewBox="0 0 20 20"><path d="M10 2.5 16 5v4.7c0 3.5-2.5 6.1-6 7.8-3.5-1.7-6-4.3-6-7.8V5l6-2.5Z" /><path d="m7.5 9.8 1.7 1.7 3.5-3.7" /></svg>
            </span>
            <div>
                <strong>VirusTotal SHA-256 reputation</strong>
                @if ($fileCheck->vt_last_checked_at)
                    <p>Status: {{ str($fileCheck->vt_status ?? 'unavailable')->replace('_', ' ')->headline() }}</p>
                    @if ($fileCheck->vt_status === 'malicious' || $fileCheck->vt_status === 'suspicious')
                        <p>{{ $fileCheck->vt_malicious }} malicious, {{ $fileCheck->vt_suspicious }} suspicious, {{ $fileCheck->vt_harmless }} harmless, and {{ $fileCheck->vt_undetected }} undetected engine results.</p>
                    @elseif ($fileCheck->vt_status === 'unknown')
                        <p>VirusTotal had no report for this SHA-256 hash.</p>
                    @elseif ($fileCheck->vt_status === 'rate_limited')
                        <p>The provider rate limit was reached. Retry later{{ $fileCheck->vt_retry_after_seconds ? ' (suggested wait: '.$fileCheck->vt_retry_after_seconds.' seconds)' : '' }}.</p>
                    @elseif ($fileCheck->vt_status === 'unavailable')
                        <p>The lookup could not be completed ({{ $fileCheck->vt_error_code ?? 'provider_error' }}).</p>
                    @elseif ($fileCheck->vt_status === 'no_threat_detected')
                        <p>{{ $fileCheck->vt_malicious }} malicious, {{ $fileCheck->vt_suspicious }} suspicious, {{ $fileCheck->vt_harmless }} harmless, and {{ $fileCheck->vt_undetected }} undetected engine results.</p>
                    @endif
                    <p>Last checked: {{ $fileCheck->vt_last_checked_at->format('M j, Y H:i:s') }} UTC</p>
                @else
                    <p>This record has not been checked. The next action sends only the SHA-256 digest to VirusTotal; it does not upload file content.</p>
                @endif
            </div>
        </x-panel>

        @if ($fileCheck->sha256)
            <form method="POST" action="{{ route('file-check.virustotal', $fileCheck) }}" class="file-check-delete-form">
                @csrf
                <button class="button button-outline button-small" type="submit">Check SHA-256 with VirusTotal</button>
            </form>
        @endif

        @if (session('ai_file_explanation'))
            <x-panel class="file-check-analysis-result" aria-live="polite">
                <p class="eyebrow">AI EXPLANATION · {{ session('ai_file_explanation.model') }}</p>
                <p>{{ session('ai_file_explanation.content') }}</p>
            </x-panel>
        @endif

        <form method="POST" action="{{ route('file-check.ai-explanation', $fileCheck) }}" class="file-check-form">
            @csrf
            <p class="form-hint">AI uses {{ $aiModels[$aiModel] ?? $aiModel }}. Only file type, size, processing status, and VirusTotal counts are sent; never file bytes, filename, storage key, or hash. Provider prompts may be used for training. <a href="{{ route('profile.edit') }}">Manage AI preferences</a>.</p>
            <button class="button button-outline button-small" type="submit" @disabled(! $aiProviderConsent)>Explain file-check results with AI</button>
            @unless ($aiProviderConsent)
                <p class="form-hint">AI is off for this account. <a href="{{ route('profile.edit') }}">Enable AI once in your profile</a>.</p>
            @endunless
        </form>

        <x-panel class="file-check-limits-panel file-check-report-warning">
            <span class="safety-icon" aria-hidden="true">
                <svg viewBox="0 0 20 20"><path d="M10 2.5 16 5v4.7c0 3.5-2.5 6.1-6 7.8-3.5-1.7-6-4.3-6-7.8V5l6-2.5Z" /><path d="M10 7v3.5m0 2.5h.01" /></svg>
            </span>
            <div>
                <strong>No malware analysis was performed</strong>
                <p>Metadata and hash reputation counts do not inspect or execute file contents, and cannot guarantee that a file is safe.</p>
            </div>
        </x-panel>

        <form method="POST" action="{{ route('file-check.destroy', $fileCheck) }}" class="file-check-delete-form"
            onsubmit="return confirm('Permanently delete this private file and its record?')">
            @csrf
            @method('DELETE')
            <button class="button button-outline button-small" type="submit">Delete file and record</button>
        </form>
    </div>
@endsection
