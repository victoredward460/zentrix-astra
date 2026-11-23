@props(['title', 'description'])

<div {{ $attributes->class(['error-state']) }} role="alert">
    <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7.5" /><path d="M10 6v4.5m0 3h.01" /></svg>
    <span><strong>{{ $title }}</strong><p>{{ $description }}</p></span>
</div>
