@props([
    'eyebrow' => null,
    'title' => null,
    'copy' => null,
    'meta' => null,
    'media' => null,
    'src' => null,
    'ratio' => 'portrait',
    'shell' => 'wide',
    'mediaAlt' => null,
    'sizes' => '(min-width: 1081px) 45vw, 100vw',
])

@php
    $hasMedia = filled($media) || filled($src);
    $hasActions = trim((string) $slot) !== '';

    $heroWebpSrcset = $hasMedia && $media && method_exists($media, 'webpSrcset')
        ? $media->webpSrcset()
        : null;
    $heroWebpUrl = $hasMedia && $media && method_exists($media, 'webpPublicUrl')
        ? $media->webpPublicUrl()
        : null;
    $heroFallbackUrl = $hasMedia
        ? ($src ?: ($media && method_exists($media, 'publicUrl') ? $media->publicUrl() : null))
        : null;
@endphp

@if ($hasMedia)
    @push('head_preload')
        @if ($heroWebpSrcset)
            <link rel="preload" as="image" type="image/webp" imagesrcset="{{ $heroWebpSrcset }}" imagesizes="{{ $sizes }}" fetchpriority="high">
        @elseif ($heroWebpUrl)
            <link rel="preload" as="image" href="{{ $heroWebpUrl }}" type="image/webp" fetchpriority="high">
        @elseif ($heroFallbackUrl)
            <link rel="preload" as="image" href="{{ $heroFallbackUrl }}" fetchpriority="high">
        @endif
    @endpush
@endif

<section {{ $attributes->class([
    'page-hero',
    'page-shell--'.$shell,
    'page-hero--split' => $hasMedia,
    'page-hero--simple' => ! $hasMedia,
]) }}>
    <div class="page-hero__inner">
        <div class="page-hero__content" data-reveal>
            @if ($eyebrow)
                <p class="eyebrow">{{ $eyebrow }}</p>
            @endif

            @if ($title)
                <h1 class="page-hero__title">{{ $title }}</h1>
            @endif

            @if ($copy)
                <p class="page-hero__copy">{{ $copy }}</p>
            @endif

            @if ($meta)
                <p class="page-meta-line">{{ $meta }}</p>
            @endif

            @if ($hasActions)
                <div class="page-hero__actions">
                    {{ $slot }}
                </div>
            @endif
        </div>

        @if ($hasMedia)
            <div class="page-hero__media" data-reveal data-reveal-delay="0.14">
                <x-editorial.media-frame
                    class="media-frame--clean"
                    :media="$media"
                    :src="$src"
                    :ratio="$ratio"
                    :alt="$mediaAlt ?: $title"
                    :sizes="$sizes"
                    loading="eager"
                />
            </div>
        @endif
    </div>
</section>
