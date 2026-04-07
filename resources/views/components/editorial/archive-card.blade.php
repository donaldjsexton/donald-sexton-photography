@props([
    'title' => null,
    'href' => null,
    'meta' => null,
    'copy' => null,
    'headingLevel' => 'h2',
])

<article data-reveal {{ $attributes->class(['archive-card']) }}>
    @if ($title)
        <{{ $headingLevel }}>
            @if ($href)
                <a href="{{ $href }}">{{ $title }}</a>
            @else
                {{ $title }}
            @endif
        </{{ $headingLevel }}>
    @endif

    @if ($meta)
        <p class="meta">{{ $meta }}</p>
    @endif

    @if ($copy)
        <p>{{ $copy }}</p>
    @endif

    {{ $slot }}
</article>
