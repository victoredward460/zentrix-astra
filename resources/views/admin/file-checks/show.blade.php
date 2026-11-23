@extends('layouts.app')

@section('title', 'File Check report')

@section('workspace')
    <div class="workspace-content file-check-content">
        <header class="workspace-heading">
            <div><p class="eyebrow">ADMINISTRATION / FILE CHECK REPORT</p><h1>File Check report</h1><p class="workspace-intro">Owner: {{ $fileCheck->user?->name ?? 'Deleted account' }} ({{ $fileCheck->user?->email ?? 'unavailable' }})</p></div>
            <a class="button button-outline button-small" href="{{ route('admin.file-checks.index') }}">Back to File Check records</a>
        </header>
        <x-panel class="dashboard-data-panel">
            <div class="dashboard-panel-heading">
                <div><p class="eyebrow">FILE METADATA</p><h2>{{ $fileCheck->original_name }}</h2></div>
                <x-status-badge tone="muted">{{ str($fileCheck->status->value)->title() }}</x-status-badge>
            </div>
            <dl class="safe-navigation-url-detail">
                <div><dt>Size</dt><dd>{{ number_format($fileCheck->size_bytes) }} bytes</dd></div>
                <div><dt>Reported media type</dt><dd>{{ $fileCheck->mime_type }}</dd></div>
                <div><dt>SHA-256</dt><dd><code>{{ $fileCheck->sha256 }}</code></dd></div>
                <div><dt>Submitted</dt><dd>{{ $fileCheck->created_at->format('M j, Y H:i:s') }}</dd></div>
                <div><dt>Processed</dt><dd>{{ $fileCheck->processed_at?->format('M j, Y H:i:s') ?? 'Not processed' }}</dd></div>
                @if ($fileCheck->failure_code)
                    <div><dt>Failure code</dt><dd>{{ $fileCheck->failure_code }}</dd></div>
                @endif
            </dl>
            <div class="form-notice" role="note">This record contains file metadata and a cryptographic hash only. No malware scan was performed, and the original file is not available in this report.</div>
        </x-panel>
    </div>
@endsection
