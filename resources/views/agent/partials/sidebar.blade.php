<aside class="agent-history-sidebar" id="agent-history-sidebar" aria-label="Conversation history">
    <div class="agent-sidebar-top">
        <a class="agent-sidebar-brand" href="{{ route('agent.index') }}">
            <span class="agent-brand-mark" aria-hidden="true">Z</span>
            <span><strong>ZENTRIX ASTRA</strong><small>AI ASSISTANT</small></span>
        </a>
        <button class="agent-history-close" type="button" aria-label="Close conversation history" data-agent-history-close>
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    <form method="POST" action="{{ route('agent.threads.store') }}" class="agent-new-chat-form">
        @csrf
        <button class="agent-new-chat-button" type="submit">
            <span aria-hidden="true">+</span>
            New chat
        </button>
    </form>

    <form method="GET" action="{{ route('agent.index') }}" role="search" class="agent-search-form">
        <label class="sr-only" for="agent-conversation-search">Search conversations</label>
        <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5" /><path d="m13 13 4 4" /></svg>
        <input id="agent-conversation-search" name="q" type="search" value="{{ $search }}" maxlength="100" placeholder="Search conversations">
        @if ($search !== '')
            <a href="{{ route('agent.index') }}" aria-label="Clear conversation search">&times;</a>
        @endif
    </form>

    <div class="agent-history-heading">
        <span>Recent</span>
        <span>{{ $threads->total() }}</span>
    </div>

    <nav class="agent-thread-list" aria-label="Recent conversations">
        @forelse ($threads as $historyThread)
            @php($isCurrentThread = isset($thread) && $thread->id === $historyThread->id)
            <a
                class="agent-thread-link {{ $isCurrentThread ? 'is-current' : '' }}"
                href="{{ route('agent.threads.show', $historyThread) }}"
                @if ($isCurrentThread) aria-current="page" @endif
            >
                <span class="agent-thread-link-title">{{ $historyThread->title }}</span>
                <span class="agent-thread-link-meta">{{ $historyThread->last_activity_at->diffForHumans() }}</span>
            </a>
        @empty
            <p class="agent-history-empty">
                {{ $search !== '' ? 'No matching conversations.' : 'Your conversations will appear here.' }}
            </p>
        @endforelse
    </nav>

    @if ($threads->hasPages())
        <div class="agent-history-pagination">{{ $threads->links() }}</div>
    @endif

    <div class="agent-sidebar-footer">
        <div class="agent-provider-status">
            <span class="agent-provider-indicator {{ $providerConfigured ? 'is-configured' : '' }}" aria-hidden="true"></span>
            <span>
                <strong>{{ $providerConfigured ? 'OpenRouter configured' : 'AI setup required' }}</strong>
                <small>{{ $providerConfigured ? 'Connection not yet verified' : 'No request will be sent' }}</small>
            </span>
        </div>
        @if ($providerConfigured && $model)
            <p class="agent-model-label">{{ $model }}</p>
        @endif
        <p class="agent-retention-note">Account-scoped here · provider may train on sent content</p>
    </div>
</aside>
