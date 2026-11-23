@extends('layouts.app')

@section('title', $thread->title)

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
                    <h1>{{ $thread->title }}</h1>
                </div>
                <details class="agent-thread-options">
                    <summary aria-label="Conversation options">Options</summary>
                    <div class="agent-thread-options-menu">
                        <form method="POST" action="{{ route('agent.threads.rename', $thread) }}">
                            @csrf
                            @method('PATCH')
                            <label for="agent-thread-title">Rename conversation</label>
                            <div class="agent-rename-controls">
                                <input id="agent-thread-title" name="title" type="text" maxlength="120" value="{{ old('title', $thread->title) }}" required>
                                <button class="button button-primary button-small" type="submit">Save</button>
                            </div>
                            @error('title')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </form>
                        <form method="POST" action="{{ route('agent.threads.destroy', $thread) }}" onsubmit="return confirm('Delete this conversation and all its messages?')">
                            @csrf
                            @method('DELETE')
                            <button class="agent-delete-thread" type="submit">Delete conversation</button>
                        </form>
                    </div>
                </details>
            </header>

            @if (session('agent_error'))
                <div class="agent-alert" role="alert">{{ session('agent_error') }}</div>
            @endif
            @if (session('status'))
                <p class="agent-status-message" role="status">{{ session('status') }}</p>
            @endif

            <section class="agent-transcript" aria-label="Conversation messages" aria-live="polite" data-agent-transcript>
                @if ($thread->messages->isEmpty())
                    <div class="agent-welcome agent-welcome-compact">
                        <span class="agent-welcome-mark" aria-hidden="true">Z</span>
                        <p class="eyebrow">PRIVATE CONVERSATION</p>
                        <h2>What would you like to understand?</h2>
                        <p>Ask a general question, explore a defensive security topic, or select one of your findings below.</p>
                    </div>
                @else
                    <div class="agent-message-list">
                        @foreach ($thread->messages as $message)
                            <article class="agent-message {{ $message->role->value === 'user' ? 'is-user' : 'is-assistant' }}">
                                <div class="agent-message-avatar {{ $message->role->value === 'user' ? 'is-user' : '' }}" aria-hidden="true">
                                    {{ $message->role->value === 'user' ? mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) : 'Z' }}
                                </div>
                                <div class="agent-message-main">
                                    <div class="agent-message-heading">
                                        <strong>{{ $message->role->value === 'user' ? 'You' : 'ZENTRIX ASTRA AI' }}</strong>
                                        <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('M j, Y H:i') }}</time>
                                        @if ($message->role->value === 'assistant' && $message->status->value === 'completed')
                                            <span class="agent-generated-label">AI-generated · explanatory only</span>
                                        @endif
                                    </div>

                                    @if ($message->status->value === 'failed')
                                        <div class="agent-failure-message" role="status">
                                            <strong>No AI answer was generated.</strong>
                                            <p>{{ $message->failureMessage() ?? 'The provider could not produce an answer.' }}</p>
                                            @php($previousMessage = $thread->messages->get($loop->index - 1))
                                            @if ($previousMessage && $previousMessage->role->value === 'user')
                                                <button class="agent-retry-button" type="button" data-agent-retry="{{ $previousMessage->content }}">
                                                    Retry this question
                                                </button>
                                            @endif
                                        </div>
                                    @elseif ($message->role->value === 'assistant')
                                        @php($renderedContent = \Illuminate\Support\Str::markdown($message->content ?? '', ['html_input' => 'strip', 'allow_unsafe_links' => false]))
                                        <div class="agent-message-content agent-markdown" id="agent-response-{{ $message->id }}">{!! $renderedContent !!}</div>
                                        <button class="agent-copy-button" type="button" data-agent-copy="agent-response-{{ $message->id }}">Copy response</button>
                                    @else
                                        <p class="agent-message-content">{{ $message->content }}</p>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif

                @if ($messages->hasPages())
                    <nav class="agent-message-pagination" aria-label="Conversation message pages">
                        {{ $messages->links() }}
                    </nav>
                @endif

                <div class="agent-thinking" role="status" aria-live="polite" hidden data-agent-thinking>
                    <span class="agent-thinking-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                    ZENTRIX ASTRA AI is thinking…
                </div>
                <div data-agent-latest></div>
            </section>

            @include('agent.partials.composer', ['action' => route('agent.threads.messages.store', $thread)])
        </main>
    </div>
@endsection
