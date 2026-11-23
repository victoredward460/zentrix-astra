@props(['id', 'label'])

<span id="{{ $id }}" {{ $attributes->class(['loading-state']) }} role="status" aria-live="polite">
    <span class="loading-spinner" aria-hidden="true"></span>
    {{ $label }}
</span>
