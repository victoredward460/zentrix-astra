@props(['tone' => 'neutral'])

<span {{ $attributes->class(['status-badge', 'status-badge--'.$tone, 'inline-flex', 'items-center']) }}>
    {{ $slot }}
</span>
