@props([
    'eyebrow' => null,
    'title' => null,
    'copy' => null,
])

<div data-reveal {{ $attributes->class(['section-header']) }}>
    @if ($eyebrow)
        <p class="eyebrow">{{ $eyebrow }}</p>
    @endif
    @if ($title)
        <h2 class="section-title">{{ $title }}</h2>
    @endif
    @if ($copy)
        <p class="section-copy">{{ $copy }}</p>
    @endif
    {{ $slot }}
</div>
