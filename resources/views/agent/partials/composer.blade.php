<form class="agent-composer" method="POST" action="{{ $action }}" data-agent-compose>
    @csrf

    @if ($findings->isNotEmpty())
        <details class="agent-context-options">
            <summary>Add a finding to the context <span>Optional</span></summary>
            <label class="sr-only" for="security_finding_id">Choose a finding from your account</label>
            <select class="form-input" id="security_finding_id" name="security_finding_id" @disabled(! $providerConfigured || ! $aiProviderConsent)>
                <option value="">No finding selected</option>
                @foreach ($findings as $finding)
                    <option value="{{ $finding->id }}" @selected(old('security_finding_id') == $finding->id)>
                        #{{ $finding->id }} · {{ $finding->severity->value }} · {{ $finding->title }}
                    </option>
                @endforeach
            </select>
            @error('security_finding_id')
                <p class="form-error">{{ $message }}</p>
            @enderror
            <p>Only a bounded summary of this finding will be sent with your message.</p>
        </details>
    @endif

    <label class="sr-only" for="agent-message-input">Message ZENTRIX ASTRA AI</label>
    <textarea
        class="agent-message-input"
        id="agent-message-input"
        name="content"
        rows="1"
        maxlength="4000"
        placeholder="{{ $providerConfigured ? ($aiProviderConsent ? 'Message ZENTRIX ASTRA AI…' : 'Enable AI in your profile to start chatting.') : 'Configure OpenRouter to enable AI messages.' }}"
        @if ($providerConfigured && $aiProviderConsent) required @endif
        @disabled(! $providerConfigured || ! $aiProviderConsent)
        data-agent-input
    >{{ old('content') }}</textarea>
    @error('content')
        <p class="form-error">{{ $message }}</p>
    @enderror

    <div class="agent-composer-footer">
        <div class="agent-model-controls">
            <label class="sr-only" for="agent-model-select">Choose AI model</label>
            <select
                class="agent-model-select"
                id="agent-model-select"
                name="model"
                data-agent-model
                data-update-url="{{ route('agent.model.update') }}"
                data-saved-model="{{ $model }}"
                aria-describedby="agent-model-status"
            >
                @foreach ($aiModels as $modelId => $modelName)
                    <option value="{{ $modelId }}" @selected($model === $modelId)>{{ $modelName }}</option>
                @endforeach
            </select>
            <span class="agent-model-status" id="agent-model-status" role="status" aria-live="polite" data-agent-model-status>
                {{ $aiModels[$model] ?? $model }}
            </span>
            @error('model')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </div>
        <button
            class="agent-send-button"
            type="submit"
            data-send-allowed="{{ $providerConfigured && $aiProviderConsent ? 'true' : 'false' }}"
            @disabled(! $providerConfigured || ! $aiProviderConsent)
        >
            <span class="agent-send-label">Send</span>
            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 10h13m-5-5 5 5-5 5" /></svg>
        </button>
    </div>
    <p class="agent-composer-disclaimer">
        Current model: <strong data-agent-current-model>{{ $aiModels[$model] ?? $model }}</strong>.
        @if ($aiProviderConsent)
            Provider data sharing is enabled; prompts may be used for training. <a href="{{ route('profile.edit') }}">Manage AI preferences</a>.
        @else
            AI is disabled until enabled once in <a href="{{ route('profile.edit') }}">your AI preferences</a>.
        @endif
        Replies are explanatory, not scan results. Do not include secrets or sensitive personal data.
    </p>
</form>
