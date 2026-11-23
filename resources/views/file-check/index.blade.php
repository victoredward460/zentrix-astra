@extends('layouts.app')

@section('title', 'File Check')

@section('workspace')
    <div class="workspace-content file-check-content">
        <header class="workspace-heading">
            <div>
                <p class="eyebrow">WORKSPACE <span aria-hidden="true">/</span> FILE CHECK</p>
                <h1>File Check</h1>
                <p class="workspace-intro">Create a private record of file metadata and its SHA-256 digest. Uploaded files are never executed, parsed, or made publicly downloadable.</p>
            </div>
        </header>

        @if (session('status'))
            <div class="form-notice file-check-notice" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="form-error file-check-error" role="alert">
                <strong>We could not complete that file check.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="file-check-panels">
            <x-panel class="file-check-upload-panel">
                <p class="eyebrow">MESSAGE ANALYSIS · LOCAL HEURISTICS</p>
                <h2>Review a suspicious message</h2>
                <p>Check text from SMS, email, or messaging apps for common pressure tactics and suspicious links. Local checks always run; you can also request an AI explanation using your saved AI preferences.</p>
                <form method="POST" action="{{ route('file-check.message-analysis') }}" class="file-check-form">
                    @csrf
                    <label for="message">Message text</label>
                    <textarea id="message" name="message" rows="7" maxlength="5000" required>{{ old('message') }}</textarea>
                    <span class="form-hint">Maximum 5,000 characters. Results are heuristic indicators, not a confirmed threat verdict.</span>
                    @if ($aiProviderConsent)
                        <label class="scanner-attestation">
                            <input type="checkbox" name="include_ai_explanation" value="1" @checked(old('include_ai_explanation'))>
                            <span>Add an AI explanation using {{ $aiModels[$aiModel] ?? $aiModel }}. If selected, this message is sent to OpenRouter and NVIDIA and may be used for training under provider policies. Remove passwords, codes, and private personal data first.</span>
                        </label>
                    @else
                        <p class="form-hint">AI explanations are off. <a href="{{ route('profile.edit') }}">Enable AI once in your profile</a>; local checks work without provider access.</p>
                    @endif
                    <button class="button button-primary button-small" type="submit">Analyze message</button>
                </form>
            </x-panel>

            <x-panel class="file-check-upload-panel">
                <p class="eyebrow">FILE METADATA · PRIVATE UPLOAD</p>
                <h2>Record file details</h2>
                <p>Any file content is treated as opaque bytes. The application stores it privately and records its content-detected MIME type and SHA-256 digest. On an explicit check, only the SHA-256 digest is sent to VirusTotal; the file content is never uploaded.</p>
                <form method="POST" action="{{ route('file-check.store') }}" enctype="multipart/form-data" class="file-check-form">
                    @csrf
                    <label for="file">Choose a file</label>
                    <input id="file" name="file" type="file" required>
                    <span class="form-hint">Maximum size: {{ number_format($maxSizeKilobytes / 1024, 0) }} MiB. A valid uploaded file is required.</span>
                    <button class="button button-primary button-small" type="submit">Record metadata</button>
                </form>
            </x-panel>

            <x-panel class="file-check-limits-panel">
                <span class="safety-icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20"><path d="M10 2.5 16 5v4.7c0 3.5-2.5 6.1-6 7.8-3.5-1.7-6-4.3-6-7.8V5l6-2.5Z" /><path d="m7.5 9.8 1.7 1.7 3.5-3.7" /></svg>
                </span>
                <div>
                    <strong>Metadata is not a malware verdict</strong>
                    <p>A completed record confirms only that file metadata and a hash were captured. It does not establish that the file is safe or malicious. Files are automatically removed after {{ $retentionDays }} days, and you can delete them sooner.</p>
                </div>
            </x-panel>
        </div>

        @if (session('message_analysis'))
            <x-panel class="file-check-analysis-result" aria-live="polite">
                <div class="dashboard-panel-heading">
                    <div>
                        <p class="eyebrow">LOCAL HEURISTIC RESULT · {{ session('message_analysis.url_count') }} URL(S) FOUND</p>
                        <h2>{{ session('message_analysis.level') }}</h2>
                    </div>
                </div>
                <p>{{ session('message_analysis.summary') }}</p>
                @if (session('message_analysis.signals'))
                    <ul>
                        @foreach (session('message_analysis.signals') as $signal)
                            <li>{{ $signal }}</li>
                        @endforeach
                    </ul>
                @endif
                @if (session('message_analysis.ai_explanation'))
                    <h3>AI explanation <span class="form-hint">via {{ session('message_analysis.ai_explanation.model') }}</span></h3>
                    <p>{{ session('message_analysis.ai_explanation.content') }}</p>
                @elseif (session('message_analysis.ai_error'))
                    <p class="form-error" role="alert">AI explanation could not be completed: {{ session('message_analysis.ai_error') }}</p>
                @endif
            </x-panel>
        @endif

        <section class="scanner-section" aria-labelledby="file-check-history-heading">
            <div class="dashboard-section-heading">
                <div>
                    <p class="eyebrow">PRIVATE ACCOUNT HISTORY</p>
                    <h2 id="file-check-history-heading">File records</h2>
                </div>
            </div>
            @if ($fileChecks->isEmpty())
                <x-empty-state
                    title="No file records yet"
                    description="When you upload a file, its private metadata record and SHA-256 result will appear here. No file contents will be executed."
                />
            @else
                <div class="scanner-history-list">
                    @foreach ($fileChecks as $fileCheck)
                        <a class="scanner-history-row file-check-history-row" href="{{ route('file-check.show', $fileCheck) }}">
                            <span>
                                <strong>{{ $fileCheck->original_name }}</strong>
                                <small>{{ number_format($fileCheck->size_bytes) }} bytes · {{ $fileCheck->created_at->format('M j, Y H:i') }}</small>
                            </span>
                            <x-status-badge tone="{{ $fileCheck->status->value === 'completed' ? 'good' : ($fileCheck->status->value === 'failed' ? 'warning' : 'muted') }}">
                                {{ str($fileCheck->status->value)->headline() }}
                            </x-status-badge>
                            <span class="scanner-history-open">View record <span aria-hidden="true">→</span></span>
                        </a>
                    @endforeach
                </div>
                <div class="scanner-pagination">{{ $fileChecks->links() }}</div>
            @endif
        </section>
    </div>
@endsection
