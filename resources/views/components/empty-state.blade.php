@props(['title', 'description'])

<div {{ $attributes->class(['empty-state', 'flex', 'flex-col', 'items-center']) }}>
    <span class="empty-state-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24">
            <path d="M4 5h16v13H4z" />
            <path d="M8 9h8M8 12h5M8 15h3" />
        </svg>
    </span>
    <h3>{{ $title }}</h3>
    <p>{{ $description }}</p>
    @if (isset($action))
        <div class="empty-state-action">{{ $action }}</div>
    @endif
</div>
