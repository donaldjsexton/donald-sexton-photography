@extends('layouts.app')

@section('title', $page?->seo_title ?: $page?->title ?: 'Collections')
@section('meta_description', $page?->seo_description ?: $page?->excerpt ?: '')
@section('canonical_url', $page?->canonical_url ?: url()->current())

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
                <p>Most couples start with enough time for getting ready, the ceremony, portraits, family photos, and the dance floor.</p>
                <p>From there, you can add welcome events, rehearsal coverage, or extra portrait time if it matters to you.</p>
            </div>

            <p class="editorial-divider">Collections</p>

            @if ($collections->isNotEmpty())
                <div class="collection-steps">
                    @foreach ($collections as $collection)
                        @php
                            $hours = null;
                            if ($collection->coverage_hours_min || $collection->coverage_hours_max) {
                                $hours = ($collection->coverage_hours_min ?? $collection->coverage_hours_max)
                                    .(($collection->coverage_hours_max && $collection->coverage_hours_min !== $collection->coverage_hours_max) ? '-'.$collection->coverage_hours_max : '')
                                    .' hours of coverage';
                            }
                            $headline = $collection->headline;
                            $summary = $collection->summary
                                ?: \Illuminate\Support\Str::words(strip_tags($collection->description ?? ''), 28);
                            $meta = collect([
                                $collection->starting_price ? (($collection->price_label ?: 'Starting at').' $'.number_format((float) $collection->starting_price, 0)) : null,
                                $hours,
                            ])->filter()->implode(' · ');
                        @endphp
                        <article data-reveal class="collection-step">
                            <p class="collection-step__index">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</p>
                            <div class="collection-step__body">
                                <p class="collection-step__meta">{{ $meta ?: 'Custom coverage available' }}</p>
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
                    <article data-reveal class="collection-step">
                        <p class="collection-step__index">01</p>
                        <div class="collection-step__body">
                            <p class="collection-step__meta">Most requested</p>
                            <h2 class="collection-step__title">Start with the full day.</h2>
                            <p class="collection-step__copy">Most couples want enough time for getting ready, the ceremony, portraits, family photos, and the dance floor.</p>
                        </div>
                    </article>
                    <article data-reveal class="collection-step">
                        <p class="collection-step__index">02</p>
                        <div class="collection-step__body">
                            <p class="collection-step__meta">Build around your plans</p>
                            <h2 class="collection-step__title">Add what you do not want to miss.</h2>
                            <p class="collection-step__copy">You can add rehearsal coverage, a welcome party, extra portrait time, or a session before the wedding.</p>
                        </div>
                    </article>
                    <article data-reveal class="collection-step">
                        <p class="collection-step__index">03</p>
                        <div class="collection-step__body">
                            <p class="collection-step__meta">Simple next step</p>
                            <h2 class="collection-step__title">Get a clear quote.</h2>
                            <p class="collection-step__copy">Send the date, the venue, and a rough guest count. From there, I can point you to the best fit.</p>
                        </div>
                    </article>
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
