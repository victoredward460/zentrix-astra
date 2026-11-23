@extends('layouts.app')

@section('title', 'AI Agent')

@section('workspace')
    <div class="agent-app" data-agent-app>
        @include('agent.partials.sidebar')
        <button class="agent-history-backdrop" type="button" aria-label="Close conversation history" data-agent-history-close></button>

        <main class="agent-main">
            <header class="agent-chat-topbar">
                <button class="agent-history-toggle" type="button" aria-expanded="true" aria-controls="agent-history-sidebar" data-agent-history-toggle>
                    <span class="sr-only">Toggle conversation history</span>
                    <svg viewBox="0 0 20 20" aria-hidden="true"><rect x="2.5" y="3" width="15" height="14" rx="2" /><path d="M7.5 3v14" /></svg>
                </button>
                <div class="agent-chat-title">
                    <p class="eyebrow">ZENTRIX ASTRA AI</p>
                    <h1>New chat</h1>
                </div>
                <form method="POST" action="{{ route('agent.threads.store') }}" class="agent-topbar-new-chat">
                    @csrf
                    <button class="button button-outline button-small" type="submit">New chat</button>
                </form>
            </header>

            @if (session('agent_error'))
                <div class="agent-alert" role="alert">{{ session('agent_error') }}</div>
            @endif
            @if (session('status'))
                <p class="agent-status-message" role="status">{{ session('status') }}</p>
            @endif

            <section class="agent-transcript agent-transcript-welcome" aria-label="New conversation" data-agent-transcript>
                <div class="agent-welcome">
                    <span class="agent-welcome-mark" aria-hidden="true">Z</span>
                    <p class="eyebrow">YOUR DEFENSIVE SECURITY ASSISTANT</p>
                    <h2>How can I help today?</h2>
                    <p>I can answer questions, explain cybersecurity concepts and Academy topics, or help you understand a finding you choose to share.</p>
                    <div class="agent-suggestions" aria-label="Suggested questions">
                        <button type="button" data-agent-suggestion="What does a Content-Security-Policy header do?" @disabled(! $providerConfigured || ! $aiProviderConsent)>Explain a security concept</button>
                        <button type="button" data-agent-suggestion="How should I interpret an unresolved security finding?" @disabled(! $providerConfigured || ! $aiProviderConsent)>Understand a finding</button>
                    </div>
                </div>
                <div class="agent-thinking" role="status" aria-live="polite" hidden data-agent-thinking>
                    <span class="agent-thinking-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                    ZENTRIX ASTRA AI is thinking…
                </div>
            </section>

            @include('agent.partials.composer', ['action' => route('agent.threads.start')])
        </main>
    </div>
@endsection
