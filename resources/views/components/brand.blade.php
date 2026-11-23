@props(['href' => '/'])

<a href="{{ $href }}" {{ $attributes->class(['brand-link']) }} aria-label="ZENTRIX ASTRA home">
    <span class="brand-mark" aria-hidden="true">
        <img src="{{ asset('brand/zentrix-astra-mark.svg') }}" alt="">
    </span>
    <span class="brand-wordmark">
        <strong>ZENTRIX</strong>
        <span>ASTRA</span>
    </span>
</a>
