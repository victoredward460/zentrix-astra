@extends('layouts.app')

@section('title', 'Configuration and integrations')

@section('workspace')
    <div class="workspace-content grid">
        <header class="workspace-heading">
            <div>
                <p class="eyebrow">ADMINISTRATION / OPERATIONS</p>
                <h1>Configuration and integrations</h1>
                <p class="workspace-intro">Runtime settings and credentials are read-only here. Secret values are never displayed, and credential presence is not a connectivity test.</p>
            </div>
        </header>

        @if (session('status'))
            <div class="form-notice" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="form-error" role="alert">
                <strong>We could not complete that configuration request.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-panel class="module-table-panel">
            <h2>Application configuration</h2>
            <p class="form-hint">Runtime configuration is managed through the local environment or deployment secret store; this page does not edit environment values.</p>
            <x-data-table class="w-full" :headers="['Setting', 'Current value', 'Verification']" caption="Non-secret application configuration">
                @foreach ($settings as $label => $value)
                    <tr>
                        <th scope="row">{{ $label }}</th>
                        <td>{{ $value }}</td>
                        <td><x-status-badge tone="muted">Configured value only</x-status-badge></td>
                    </tr>
                @endforeach
            </x-data-table>
        </x-panel>

        <x-panel class="module-table-panel">
            <h2>AI Agent</h2>
            <p>Provider: OpenRouter</p>
            <p>Credential present: <x-status-badge :tone="$aiConfigured ? 'good' : 'muted'">{{ $aiConfigured ? 'Yes' : 'No' }}</x-status-badge></p>
            <p class="form-hint">This checks configuration presence only. It does not call the provider or verify model availability, billing, or connectivity.</p>
        </x-panel>

        <x-panel class="module-table-panel">
            <h2>URL reputation providers</h2>
            <p class="form-hint">Credential values and API responses are never shown. The AI assistant provides explanations only; it is not a URL reputation source.</p>
            <x-data-table class="w-full" :headers="['Provider', 'Credential requirement', 'Credential status', 'Integration status']" caption="URL reputation provider configuration">
                @forelse ($providers as $provider)
                    <tr>
                        <th scope="row">{{ $provider['name'] }}</th>
                        <td>
                            @if ($provider['credential_required'])
                                <code>{{ $provider['credential_variable'] }}</code>
                            @else
                                No API key required
                            @endif
                        </td>
                        <td>
                            @if ($provider['credential_required'])
                                <x-status-badge :tone="$provider['credential_configured'] ? 'good' : 'muted'">{{ $provider['credential_configured'] ? 'Present' : 'Missing' }}</x-status-badge>
                            @else
                                <x-status-badge tone="muted">Not applicable</x-status-badge>
                            @endif
                        </td>
                        <td>
                            <x-status-badge :tone="$provider['state'] === 'configured_not_tested' ? 'accent' : 'muted'">
                                {{ $provider['state_label'] }}
                            </x-status-badge>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">No URL reputation providers are configured.</td></tr>
                @endforelse
            </x-data-table>
        </x-panel>

    </div>
@endsection
