@props([
    'title' => null,
    'href' => null,
    'meta' => null,
    'copy' => null,
    'headingLevel' => 'h2',
    'media' => null,
    'mediaSrc' => null,
    'mediaAlt' => null,
    'mediaRatio' => 'landscape',
    'mediaLoading' => 'lazy',
])

@php
    $hasMedia = $mediaSrc || ($media && ($media->path ?? null));
@endphp

<article data-reveal {{ $attributes->class(['archive-card', 'archive-card--with-media' => $hasMedia]) }}>
    @if ($hasMedia)
        @if ($href)
            <a class="archive-card__media" href="{{ $href }}" aria-hidden="true" tabindex="-1">
                <x-editorial.media-frame
                    :media="$media"
                    :src="$mediaSrc"
                    :alt="$mediaAlt ?: $title"
                    :ratio="$mediaRatio"
                    :loading="$mediaLoading"
                    class="media-frame--clean"
                />
            </a>
        @else
            <div class="archive-card__media">
                <x-editorial.media-frame
                    :media="$media"
                    :src="$mediaSrc"
                    :alt="$mediaAlt ?: $title"
                    :ratio="$mediaRatio"
                    :loading="$mediaLoading"
                    class="media-frame--clean"
                />
            </div>
        @endif
    @endif

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
