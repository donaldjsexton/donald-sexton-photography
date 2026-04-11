@props([
    'variant' => 'default',
    'eyebrow' => null,
])

@php
    $panelClasses = collect(['admin-card'])
        ->when($variant === 'metric', fn ($classes) => $classes->push('admin-card--metric'))
        ->when($variant === 'feature', fn ($classes) => $classes->push('admin-card--feature'))
        ->implode(' ');
@endphp

<article {{ $attributes->class($panelClasses) }}>
    @if ($eyebrow)
        <p class="eyebrow">{{ $eyebrow }}</p>
    @endif

    {{ $slot }}
</article>
