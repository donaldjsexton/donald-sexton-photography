@props([
    'content',
    'eyebrow' => null,
    'intro' => null,
])

@php
    $leadStory = $content->leadStory();
    $secondStory = $content->secondStory();
    $thirdStory = $content->thirdStory();

    $leadStoryImage = $leadStory?->featuredImageUrl();
    $secondStoryImage = $secondStory?->featuredImageUrl();
    $thirdStoryImage = $thirdStory?->featuredImageUrl();

    $homeHeroSizes = '(min-width: 981px) 33vw, 100vw';

    $leadMedia = $leadStory?->heroMedia;
    $leadWebp = $leadMedia && method_exists($leadMedia, 'webpPublicUrl') ? $leadMedia->webpPublicUrl() : null;
    $leadWebpSrcset = $leadMedia && method_exists($leadMedia, 'webpSrcset') ? $leadMedia->webpSrcset() : null;

    $introCopy = $intro ?: ($content->settings()?->hero_heading ?? 'Calm wedding photos that feel like your real day.');
    $heroEyebrow = $eyebrow ?: 'Wedding Photography';
@endphp

@push('head_preload')
    @if ($leadWebpSrcset)
        <link rel="preload" as="image" type="image/webp" imagesrcset="{{ $leadWebpSrcset }}" imagesizes="{{ $homeHeroSizes }}" fetchpriority="high">
    @elseif ($leadWebp)
        <link rel="preload" as="image" href="{{ $leadWebp }}" type="image/webp" fetchpriority="high">
    @elseif ($leadStoryImage)
        <link rel="preload" as="image" href="{{ $leadStoryImage }}" fetchpriority="high">
    @endif
@endpush

<section class="home-hero">
    <div class="page-shell--wide home-hero__stage">
        @if ($leadStoryImage || $secondStoryImage || $thirdStoryImage)
            <div class="home-hero__triptych">
                @if ($leadStoryImage)
                    <x-editorial.media-frame
                        class="media-frame--clean home-hero__panel home-hero__panel--left"
                        :media="$leadStory?->heroMedia"
                        :src="$leadStoryImage"
                        ratio="portrait"
                        :alt="$leadStory?->title"
                        :sizes="$homeHeroSizes"
                    />
                @endif
                @if ($secondStoryImage)
                    <x-editorial.media-frame
                        class="media-frame--clean home-hero__panel home-hero__panel--center"
                        :media="$secondStory?->heroMedia"
                        :src="$secondStoryImage"
                        ratio="portrait"
                        :alt="$secondStory?->title ?? $leadStory?->title"
                        :sizes="$homeHeroSizes"
                    />
                @endif
                @if ($thirdStoryImage)
                    <x-editorial.media-frame
                        class="media-frame--clean home-hero__panel home-hero__panel--right"
                        :media="$thirdStory?->heroMedia"
                        :src="$thirdStoryImage"
                        ratio="portrait"
                        :alt="$thirdStory?->title ?? $secondStory?->title"
                        :sizes="$homeHeroSizes"
                    />
                @endif
            </div>
        @endif

        @if ($leadStoryImage)
            <div class="home-hero__solo">
                <x-editorial.media-frame
                    class="media-frame--clean home-hero__solo-frame"
                    :media="$leadStory?->heroMedia"
                    :src="$leadStoryImage"
                    ratio="cinema"
                    :alt="$leadStory?->title"
                    :sizes="$homeHeroSizes"
                />
            </div>
        @endif

        <div class="home-hero__wordmark" data-reveal>
            <span class="home-hero__wordmark-line">Donald</span>
            <span class="home-hero__wordmark-line">Sexton</span>
            <span class="home-hero__wordmark-subline">Photography</span>
        </div>
    </div>

    <div class="home-hero__divider" aria-hidden="true"></div>

    <div class="page-shell--tight home-hero__intro" data-reveal data-reveal-delay="0.18">
        <p class="eyebrow">{{ $heroEyebrow }}</p>
        <p class="home-hero__intro-copy">{{ $introCopy }}</p>
        <div class="home-hero__intro-meta">
            <span>Clearwater</span>
            <span>Tampa</span>
            <span>Destination</span>
        </div>
        <div class="cta-row">
            <a class="cta" href="{{ route('inquiry.create') }}">Check Availability</a>
            <a class="cta-secondary" href="{{ route('weddings.index') }}">See Wedding Stories</a>
        </div>
        <p class="home-hero__reassurance meta">A real reply within 24 hours.</p>
    </div>
</section>
