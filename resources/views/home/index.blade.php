@extends('layouts.app')

@php
    $homeVisualStoryPool = $homeStories
        ->concat($featuredStories)
        ->unique('id')
        ->filter(fn ($story) => filled($story?->featuredImageUrl()))
        ->values();
    $homeLeadStory = $homeVisualStoryPool->get(0) ?? $homeStories->get(0) ?? $featuredStories->get(0);
    $homeLeadImage = $homeLeadStory?->featuredImageUrl();
    $homeLeadMedia = $homeLeadStory?->heroMedia;
    $homeLeadWebp = $homeLeadMedia && method_exists($homeLeadMedia, 'webpPublicUrl')
        ? $homeLeadMedia->webpPublicUrl()
        : null;
    $homeLeadWebpSrcset = $homeLeadMedia && method_exists($homeLeadMedia, 'webpSrcset')
        ? $homeLeadMedia->webpSrcset()
        : null;
    $homeHeroSizes = '(min-width: 981px) 33vw, 100vw';
@endphp

@section('title', 'Donald Sexton Photography')
@section('meta_description', 'Calm wedding photography for Clearwater, Tampa, and beyond. Real wedding stories, planning guidance, and straightforward next steps.')
@section('canonical_url', url()->current())
@section('og_image', $homeLeadImage ?: '')
@section('og_image_alt', 'Donald Sexton Photography')
@section('body_class', 'home-page')

@push('head_preload')
    @if ($homeLeadWebpSrcset)
        <link rel="preload" as="image" type="image/webp" imagesrcset="{{ $homeLeadWebpSrcset }}" imagesizes="{{ $homeHeroSizes }}" fetchpriority="high">
    @elseif ($homeLeadWebp)
        <link rel="preload" as="image" href="{{ $homeLeadWebp }}" type="image/webp" fetchpriority="high">
    @elseif ($homeLeadImage)
        <link rel="preload" as="image" href="{{ $homeLeadImage }}" fetchpriority="high">
    @endif
@endpush

@section('content')
    @php
        $visualStoryPool = $homeVisualStoryPool;
        $leadStory = $visualStoryPool->get(0) ?? $homeStories->get(0) ?? $featuredStories->get(0);
        $secondStory = $visualStoryPool->get(1) ?? $homeStories->get(1) ?? $featuredStories->get(1) ?? $leadStory;
        $thirdStory = $visualStoryPool->get(2) ?? $homeStories->get(2) ?? $featuredStories->get(2) ?? $secondStory;
        $discoverLeftStory = $visualStoryPool->get(3) ?? $homeStories->get(3) ?? $visualStoryPool->get(1);
        $discoverRightStory = $visualStoryPool->get(4) ?? $homeStories->get(4) ?? $visualStoryPool->get(2);
        $leadStoryImage = $leadStory?->featuredImageUrl();
        $secondStoryImage = $secondStory?->featuredImageUrl();
        $thirdStoryImage = $thirdStory?->featuredImageUrl();
        $discoverLeftImage = $discoverLeftStory?->featuredImageUrl();
        $discoverRightImage = $discoverRightStory?->featuredImageUrl();
        $portfolioStories = $visualStoryPool->take(3)->count() === 3
            ? $visualStoryPool->take(3)
            : $featuredStories->take(3);
        $journalPosts = $featuredJournalPosts->take(3);
        $quote = $featuredTestimonials->first();
        $discoverCopy = $settings?->hero_subheading
            ?: 'Wedding photos that feel calm, true, and easy to come back to.';
    @endphp

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
            <p class="eyebrow">Wedding Photography</p>
            <p class="home-hero__intro-copy">{{ $settings?->hero_heading ?? 'Calm wedding photos that feel like your real day.' }}</p>
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

    <section class="home-statement" data-reveal>
        <div class="page-shell--tight home-statement__inner">
            @if ($quote)
                <blockquote class="home-statement__quote">{{ $quote->quote }}</blockquote>
                <p class="home-statement__author">{{ $quote->author_name }}{{ $quote->author_context ? ' · '.$quote->author_context : '' }}</p>
            @endif

            <div class="home-statement__rail">
                @forelse ($collections as $collection)
                    <span>{{ $collection->name }}</span>
                @empty
                    <span>Wedding Coverage</span>
                    <span>Editorial Portraits</span>
                    <span>Destination Work</span>
                @endforelse
            </div>
        </div>
    </section>

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

    <section class="home-portfolio" data-reveal>
        <div class="page-shell--wide page-stack">
            <x-editorial.section-heading
                class="section-header--centered home-portfolio__header"
                eyebrow="Wedding Stories"
                title="A few good places to start."
                copy="These stories show how a full wedding day can feel in photos."
            />

            <div class="portfolio-list home-portfolio__list">
                @if ($leadStory)
                    <x-editorial.story-feature
                        class="story-feature--lead home-portfolio__feature"
                        :story="$leadStory"
                        ratio="cinema"
                        show-excerpt
                    />
                @endif

                @foreach ($portfolioStories->slice(1) as $story)
                    <x-editorial.story-feature
                        class="home-portfolio__feature"
                        :story="$story"
                        :reverse="$loop->odd"
                        ratio="{{ $loop->even ? 'landscape' : 'cinema' }}"
                    />
                @endforeach

                @if ($portfolioStories->isEmpty())
                    <x-editorial.empty-state
                        eyebrow="Portfolio"
                        title="New wedding stories are on the way."
                        copy="You can still look through the journal or check availability."
                        :primary-href="route('weddings.index')"
                        primary-label="See Wedding Stories"
                        :secondary-href="route('inquiry.create')"
                        secondary-label="Check Availability"
                    />
                @endif
            </div>
        </div>
    </section>

    <section class="section home-journal" data-reveal>
        <div class="page-shell--wide page-stack">
            <x-editorial.section-heading
                eyebrow="Journal"
                title="Ideas, places, and real wedding days."
                copy="Use the journal for planning tips, venue notes, and recent stories."
            />

            <div class="archive-grid">
                @forelse ($journalPosts as $post)
                    <x-editorial.archive-card
                        :title="$post->title"
                        :href="route('journal.show', $post->slug)"
                        :meta="$post->post_type_label.($post->published_at ? ' · '.$post->published_at->format('F j, Y') : '')"
                        :copy="method_exists($post, 'summaryText') ? $post->summaryText(24) : ($post->excerpt ?: \Illuminate\Support\Str::words(strip_tags($post->body ?? ''), 24))"
                    />
                @empty
                    <x-editorial.empty-state
                        eyebrow="Journal"
                        title="New journal posts are on the way."
                        copy="For now, start with the wedding stories or check availability."
                        :primary-href="route('weddings.index')"
                        primary-label="See Wedding Stories"
                    />
                @endforelse
            </div>
        </div>
    </section>

    <x-editorial.google-reviews />

    <section class="section home-inline-inquiry" data-reveal>
        <div class="page-shell--tight home-inline-inquiry__inner">
            <div class="home-inline-inquiry__intro">
                <p class="eyebrow">Start Your Inquiry</p>
                <h2 class="section-title">Check your date in 30 seconds.</h2>
                <p class="section-copy">Pop in your name, email, and the day you have in mind. I will take it from there — usually within 24 hours.</p>
            </div>

            <form class="home-inline-inquiry__form" method="GET" action="{{ route('inquiry.create') }}" data-inline-inquiry>
                <label>
                    Your name
                    <input type="text" name="primary_name" autocomplete="name" required>
                </label>
                <label>
                    Email
                    <input type="email" name="email" autocomplete="email" required>
                </label>
                <label>
                    Date
                    <input type="date" name="event_date">
                </label>
                <button class="cta" type="submit">Continue</button>
                <p class="meta home-inline-inquiry__note">Takes you to the full form with your details ready.</p>
            </form>
        </div>
    </section>

    <x-editorial.page-closing
        eyebrow="Next Step"
        title="Have a date in mind?"
        copy="Send your date, venue, and a short note. I will guide you from there — most inquiries get a reply within 24 hours."
        :primary-href="route('inquiry.create')"
        primary-label="Check Availability"
        :secondary-href="route('weddings.index')"
        secondary-label="See Wedding Stories"
    />
@endsection
