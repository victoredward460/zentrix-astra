@props(['id', 'title'])

<dialog id="{{ $id }}" class="modal" aria-labelledby="{{ $id }}-title">
    <div class="modal-content">
        <button class="modal-close" type="button" aria-label="Close dialog" data-modal-close>
            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15" /></svg>
        </button>
        <p class="modal-kicker">ZENTRIX ASTRA</p>
        <h2 id="{{ $id }}-title">{{ $title }}</h2>
        <div class="modal-copy">{{ $slot }}</div>
        @if (isset($actions))
            <div class="modal-actions">{{ $actions }}</div>
        @endif
    </div>
</dialog>
