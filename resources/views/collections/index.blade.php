@extends('layouts.app')

@section('title', $page?->seo_title ?: $page?->title ?: 'Collections')
@section('meta_description', $page?->seo_description ?: $page?->excerpt ?: '')
@section('canonical_url', $page?->canonical_url ?: url()->current())
@section('og_image', $page?->heroMedia?->publicUrl() ?: '')
@section('og_image_alt', $page?->title ?: 'Collections')

@section('content')
    <x-editorial.page-hero
        class="page-hero--archive-intro"
        eyebrow="Collections"
        :title="$page?->title ?? 'Collections'"
        :copy="$page?->excerpt ?? 'Start with the coverage that fits your day, then add what matters most.'"
        :media="$page?->heroMedia"
        ratio="portrait"
    >
        <div class="cta-row">
            <a class="cta" href="{{ route('inquiry.create') }}">Check Availability</a>
            <a class="cta-secondary" href="{{ route('weddings.index') }}">See Wedding Stories</a>
        </div>
    </x-editorial.page-hero>

    <section class="section">
        <div class="page-shell--wide page-stack page-stack--compact">
            <div class="collection-intro" data-reveal>
                <p>Documentary wedding photography that captures your day as it unfolds. Your coverage is built on hours—start with what fits your timeline, then add what matters most.</p>
            </div>

            <p class="editorial-divider">Coverage Options</p>

            @if ($collections->isNotEmpty())
                <div class="collection-steps">
                    @foreach ($collections as $collection)
                        @php
                            $hours = null;
                            $min = $collection->coverage_hours_min;
                            $max = $collection->coverage_hours_max;
                            if ($min || $max) {
                                if ($min && $max && $min !== $max) {
                                    $hours = $min.'-'.$max.' hours';
                                } elseif ($min && ! $max) {
                                    $hours = $min.'+ hours';
                                } else {
                                    $hours = ($min ?? $max).' hours';
                                }
                            }
                            $headline = $collection->headline;
                            $summary = $collection->summary
                                ?: \Illuminate\Support\Str::words(strip_tags($collection->description ?? ''), 28);
                            $price = $collection->starting_price ? '$'.number_format((float) $collection->starting_price, 0) : null;
                            $priceLabel = $collection->price_label ?: 'Starting at';
                        @endphp
                        <article data-reveal class="collection-step-pricing">
                            <div class="collection-step__body">
                                @if ($hours || $price)
                                    <div class="collection-step__price-row">
                                        @if ($hours)
                                            <p class="collection-step__hours">{{ $hours }}</p>
                                        @endif
                                        @if ($price)
                                            <p class="collection-step__price">{{ $priceLabel }} {{ $price }}</p>
                                        @endif
                                    </div>
                                @endif
                                <h2 class="collection-step__title">{{ $collection->name }}</h2>
                                @if ($headline)
                                    <p class="collection-step__headline">{{ $headline }}</p>
                                @endif
                                @if ($summary)
                                    <p class="collection-step__copy">{{ $summary }}</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="collection-steps">
                    <article data-reveal class="collection-step-pricing">
                        <div class="collection-step__body">
                            <div class="collection-step__price-row">
                                <p class="collection-step__hours">6 hours</p>
                                <p class="collection-step__price">Starting at $3,800</p>
                            </div>
                            <h2 class="collection-step__title">Essential</h2>
                            <p class="collection-step__headline">Full day, core moments.</p>
                            <p class="collection-step__copy">Getting ready, ceremony, family photos, portraits, and reception coverage. Everything you need to document the day.</p>
                        </div>
                    </article>
                    <article data-reveal class="collection-step-pricing">
                        <div class="collection-step__body">
                            <div class="collection-step__price-row">
                                <p class="collection-step__hours">8 hours</p>
                                <p class="collection-step__price">Starting at $5,200</p>
                            </div>
                            <h2 class="collection-step__title">Complete</h2>
                            <p class="collection-step__headline">All day, all moments.</p>
                            <p class="collection-step__copy">Everything in Essential, plus rehearsal coverage, pre-ceremony prep, and first looks. Capture the full narrative from beginning to end.</p>
                        </div>
                    </article>
                    <article data-reveal class="collection-step-pricing">
                        <div class="collection-step__body">
                            <div class="collection-step__price-row">
                                <p class="collection-step__hours">10+ hours</p>
                                <p class="collection-step__price">Starting at $6,800</p>
                            </div>
                            <h2 class="collection-step__title">Extended</h2>
                            <p class="collection-step__headline">Full coverage, no limits.</p>
                            <p class="collection-step__copy">Complete coverage plus welcome events, post-ceremony portraits, details, and extended reception. For weddings that demand nothing be missed.</p>
                        </div>
                    </article>
                </div>

                <div class="collection-addons" data-reveal>
                    <p class="collection-addons__heading">Add-ons</p>
                    <ul class="collection-addons__list">
                        <li>Second shooter (+$1,200)</li>
                        <li>Engagement session (+$600)</li>
                        <li>Rehearsal coverage (+$500)</li>
                        <li>Getting ready only (+$400)</li>
                        <li>Additional hour (+$650)</li>
                        <li>Custom coverage (ask in inquiry)</li>
                    </ul>
                </div>
            @endif

            @if ($page?->body)
                <x-editorial.reading-section>
                    {!! $page->body !!}
                </x-editorial.reading-section>
            @endif
        </div>
    </section>

    <x-editorial.page-closing
        eyebrow="Next Step"
        title="Want help choosing the right fit?"
        copy="Send your date, venue, and guest count. I will point you to the best option."
        :primary-href="route('inquiry.create')"
        primary-label="Check Availability"
        :secondary-href="route('weddings.index')"
        secondary-label="See Wedding Stories"
    />
@endsection
