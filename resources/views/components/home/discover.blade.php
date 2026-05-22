@props([
    'content',
    'copy' => null,
])

@php
    $discoverLeftStory = $content->discoverLeftStory();
    $discoverRightStory = $content->discoverRightStory();
    $discoverLeftImage = $discoverLeftStory?->featuredImageUrl();
    $discoverRightImage = $discoverRightStory?->featuredImageUrl();
    $discoverCopy = $copy
        ?: ($content->settings()?->hero_subheading ?: 'Wedding photos that feel calm, true, and easy to come back to.');
@endphp

<section class="home-discover" data-reveal>
    <div class="page-shell--wide home-discover__grid">
        @if ($discoverLeftImage)
            <x-editorial.media-frame
                class="media-frame--clean home-discover__image home-discover__image--left"
                :media="$discoverLeftStory?->heroMedia"
                :src="$discoverLeftImage"
                ratio="portrait"
                :alt="$discoverLeftStory?->title"
            />
        @endif

        <div class="home-discover__type">
            <p class="eyebrow">Favorite Stories</p>
            <div class="home-discover__headline">
                <span class="home-discover__headline-part home-discover__headline-part--italic">Photos</span>
                <span class="home-discover__headline-part">that still</span>
                <span class="home-discover__headline-part">feel like</span>
                <span class="home-discover__headline-part">your day.</span>
            </div>
            <p class="home-discover__copy">{{ $discoverCopy }}</p>
        </div>

        @if ($discoverRightImage)
            <x-editorial.media-frame
                class="media-frame--clean home-discover__image home-discover__image--right"
                :media="$discoverRightStory?->heroMedia"
                :src="$discoverRightImage"
                ratio="portrait"
                :alt="$discoverRightStory?->title"
            />
        @endif
    </div>
</section>
