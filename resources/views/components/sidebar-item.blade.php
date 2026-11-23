@props([
    'href' => null,
    'active' => false,
    'disabled' => false,
    'status' => null,
    'admin' => false,
])

@php
    $itemClasses = 'sidebar-item';
    $itemLabel = trim(strip_tags((string) $slot));
    if ($active) {
        $itemClasses .= ' is-active';
    }
    if ($disabled || ! $href) {
        $itemClasses .= ' is-disabled';
    }
@endphp

@if ($disabled || ! $href)
    <button class="{{ $itemClasses }}"
        type="button"
        disabled
        aria-disabled="true"
        aria-label="{{ $itemLabel.', '.($status ?? ($admin ? 'administrator only' : 'planned for a later phase')) }}"
        title="{{ $status ?? ($admin ? 'Administrator-only module planned for a later phase.' : 'Module planned for a later phase.') }}">
        <span class="sidebar-item-main">{{ $slot }}</span>
        @if ($admin)
            <span class="sidebar-admin-tag">ADMIN</span>
        @elseif ($status)
            <span class="sidebar-item-status">{{ $status }}</span>
        @endif
    </button>
@else
    <a class="{{ $itemClasses }}" href="{{ $href }}" @if ($active) aria-current="page" @endif>
        <span class="sidebar-item-main">{{ $slot }}</span>
        @if ($status)
            <span class="sidebar-item-status">{{ $status }}</span>
        @endif
    </a>
@endif
