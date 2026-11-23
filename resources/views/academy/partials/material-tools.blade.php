<section class="academy-category" aria-labelledby="academy-material-generator-heading">
    <div class="dashboard-section-heading">
        <div>
            <p class="eyebrow">ACADEMY AI · YOUR STUDY MATERIAL</p>
            <h2 id="academy-material-generator-heading">Create an HTML + PDF study guide</h2>
        </div>
    </div>

    <x-panel class="academy-admin-form-panel">
        <p>Generate a structured cybersecurity guide with examples and code snippets when appropriate. The selected model is {{ $aiModels[$aiModel] ?? $aiModel }}. AI sharing must be enabled in your profile first. A signed link lets anyone with it view the material; do not include secrets or private information.</p>
        @unless ($aiProviderConsent)
            <p class="form-hint">AI is off for this account. <a href="{{ route('profile.edit') }}">Enable AI sharing in your profile</a> to create a guide.</p>
        @endunless
        @if ($errors->has('ai_material'))
            <div class="form-error" role="alert">{{ $errors->first('ai_material') }}</div>
        @endif
        @if (session('material-share-url'))
            <div class="academy-share-notice" role="status">
                <strong>Published share link</strong>
                <a href="{{ session('material-share-url') }}" target="_blank" rel="noopener noreferrer">Open the learning material</a>
                <input class="auth-input" type="url" readonly value="{{ session('material-share-url') }}" aria-label="Copyable share URL">
            </div>
        @endif
        <form method="POST" action="{{ route('academy.materials.generate') }}" class="academy-admin-form">
            @csrf
            <label>Topic <input class="auth-input" name="topic" value="{{ old('topic') }}" maxlength="160" required placeholder="Secure account recovery"></label>
            <label>Level
                <select class="auth-input" name="level" required>
                    @foreach (['beginner', 'intermediate', 'advanced'] as $level)
                        <option value="{{ $level }}" @selected(old('level', 'beginner') === $level)>{{ ucfirst($level) }}</option>
                    @endforeach
                </select>
            </label>
            <button class="button button-primary button-small" type="submit" @disabled(! $aiProviderConsent)>Generate HTML + PDF</button>
        </form>
    </x-panel>

    @if ($materials->isNotEmpty())
        <section class="academy-admin-section" aria-labelledby="academy-your-materials-heading">
            <div class="dashboard-section-heading">
                <div><p class="eyebrow">PRIVATE FILES · SIGNED SHARING</p><h3 id="academy-your-materials-heading">Your generated materials</h3></div>
            </div>
            @foreach ($materials as $material)
                @php($shareUrl = \Illuminate\Support\Facades\URL::signedRoute('academy.materials.shared', ['material' => $material->public_id]))
                <x-panel class="academy-admin-form-panel">
                    <div class="academy-admin-title-row">
                        <div>
                            <h3>{{ $material->title }}</h3>
                            <p class="form-hint">{{ ucfirst($material->level) }} · Created {{ $material->created_at->format('M j, Y') }}</p>
                        </div>
                        <x-status-badge tone="good">Published link</x-status-badge>
                    </div>
                    <div class="academy-material-actions">
                        <a class="button button-outline button-small" href="{{ route('academy.materials.download', [$material, 'html']) }}">Download HTML</a>
                        <a class="button button-outline button-small" href="{{ route('academy.materials.download', [$material, 'pdf']) }}">Download PDF</a>
                        <a class="button button-primary button-small" href="{{ $shareUrl }}" target="_blank" rel="noopener noreferrer">Open share link</a>
                    </div>
                    <label class="auth-field">Share URL
                        <input class="auth-input" type="url" readonly value="{{ $shareUrl }}">
                    </label>
                    <form method="POST" action="{{ route('academy.materials.destroy', $material) }}" onsubmit="return confirm('Delete this material and revoke its share link?')">
                        @csrf
                        @method('DELETE')
                        <button class="button button-outline button-small" type="submit">Delete and revoke link</button>
                    </form>
                </x-panel>
            @endforeach
        </section>
    @endif
</section>
