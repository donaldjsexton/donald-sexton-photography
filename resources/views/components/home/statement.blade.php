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
