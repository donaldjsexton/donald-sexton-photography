@props([
    'eyebrow' => 'Continue',
    'title' => null,
    'copy' => null,
    'primaryHref' => null,
    'primaryLabel' => null,
    'secondaryHref' => null,
    'secondaryLabel' => null,
])

<div data-reveal {{ $attributes->class(['empty-state']) }}>
    @if ($eyebrow)
        <p class="eyebrow">{{ $eyebrow }}</p>
    @endif

    @if ($title)
        <h2 class="feature-title">{{ $title }}</h2>
    @endif

    @if ($copy)
        <p class="section-copy">{{ $copy }}</p>
    @endif

    @if ($primaryHref || $secondaryHref)
        <div class="cta-row">
            @if ($primaryHref && $primaryLabel)
                <a class="cta" href="{{ $primaryHref }}">{{ $primaryLabel }}</a>
            @endif
            @if ($secondaryHref && $secondaryLabel)
                <a class="cta-secondary" href="{{ $secondaryHref }}">{{ $secondaryLabel }}</a>
            @endif
        </div>
    @endif
</div>
