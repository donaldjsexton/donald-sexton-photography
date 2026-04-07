@props([
    'eyebrow' => 'Full Gallery',
    'title' => 'Open the full gallery in a new tab.',
    'copy' => 'If that is the easiest way to view it, use the link below.',
    'href' => null,
    'label' => 'Open Full Gallery',
    'secondaryHref' => null,
    'secondaryLabel' => null,
    'note' => null,
])

<section class="section">
    <div class="page-shell--tight">
        <div data-reveal {{ $attributes->class(['external-gallery-fallback']) }}>
            <p class="eyebrow">{{ $eyebrow }}</p>
            <h2 class="section-title">{{ $title }}</h2>
            <p class="section-copy">{{ $copy }}</p>

            @if ($href || $secondaryHref)
                <div class="cta-row">
                    @if ($href)
                        <a class="cta" href="{{ $href }}" target="_blank" rel="noreferrer"> {{ $label }} </a>
                    @endif

                    @if ($secondaryHref)
                        <a class="cta-secondary" href="{{ $secondaryHref }}"> {{ $secondaryLabel }} </a>
                    @endif
                </div>
            @endif

            @if ($note)
                <p class="meta">{{ $note }}</p>
            @endif
        </div>
    </div>
</section>
