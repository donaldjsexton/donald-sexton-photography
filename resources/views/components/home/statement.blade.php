@props([
    'content',
])

@php
    $quote = $content->quote();
    $collections = $content->collections();
@endphp

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

@php
    $startingPrice = $collections->min('starting_price');
    $startingLabel = $startingPrice ? '$'.number_format((float) $startingPrice, 0) : '$2,100';
@endphp

<section class="home-about" data-reveal>
    <div class="page-shell--tight home-about__inner">
        <p class="eyebrow">About</p>
        <h2 class="section-title">Based in Clearwater.</h2>
        <div class="home-about__copy">
            <p>I am Donald Sexton, a wedding photographer based in Clearwater. I work across Tampa, the Gulf Coast, and beyond, photographing weddings, engagements, and elopements with a calm, simple approach. I want the photos to feel honest, calm, and full of life. I pay attention to people first, then to light, timing, and the small moments that give the day its shape. Some couples want big portraits. Some want gentle direction and room to breathe. Most want both. The work holds all of that without making the day feel staged or heavy.</p>
            <p>The days I photograph most often are in Clearwater, on Clearwater Beach, on <a href="{{ route('venues.show', 'romantic-honeymoon-island-weddings') }}">Honeymoon Island in Dunedin</a>, and around Tampa Bay, St. Petersburg included. That covers beach elopements, days at Knotted Roots on the Lake, and the Krysinsky wedding at <a href="{{ route('venues.show', 'hilton-clearwater-beach-resort-and-spa') }}">Hilton Clearwater Beach Resort &amp; Spa</a>. Elopements and smaller weddings are welcome. <a href="{{ route('collections.index') }}">Collections</a> start at {{ $startingLabel }}. If you want photographs that feel easy to live with for years, <a href="{{ route('pages.about') }}">read a little more</a> or send the date, the place, and what matters most.</p>
        </div>
    </div>
</section>
