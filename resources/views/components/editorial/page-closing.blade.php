@props([
    'eyebrow' => 'Next Step',
    'title' => 'Ready to talk about your day?',
    'copy' => 'Share your date, venue, and a short note. We can figure out the rest together.',
    'primaryHref' => null,
    'primaryLabel' => null,
    'secondaryHref' => null,
    'secondaryLabel' => null,
])

<section data-reveal {{ $attributes->class(['section']) }}>
    <div class="page-shell--tight page-closing">
        <p class="eyebrow">{{ $eyebrow }}</p>
        <h2 class="section-title">{{ $title }}</h2>
        <p class="section-copy">{{ $copy }}</p>

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
</section>
