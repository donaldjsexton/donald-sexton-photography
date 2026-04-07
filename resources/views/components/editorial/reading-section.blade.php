@props([
    'shell' => 'detail',
])

@php
    $shellClass = match ($shell) {
        'wide' => 'detail-shell--wide',
        default => 'detail-shell',
    };
@endphp

<section data-reveal {{ $attributes->class(['section']) }}>
    <div class="{{ $shellClass }} rich-text">
        {{ $slot }}
    </div>
</section>
