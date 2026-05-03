@extends('layouts.app')

@php
    $firstWeddingImage = $stories->getCollection()
        ->map(fn ($story) => $story->featuredImageUrl())
        ->first(fn ($url) => filled($url));
@endphp

@section('title', 'Weddings')
@section('og_image', $firstWeddingImage ?: '')
@section('og_image_alt', 'Wedding stories by Donald Sexton Photography')

@section('content')
    @php
        $leadStory = $stories->getCollection()->first();
        $secondaryStories = $stories->getCollection()->slice(1);
    @endphp

    <x-editorial.page-hero
        eyebrow="Portfolio"
        title="Wedding stories you can get lost in."
        copy="Real wedding days, from quiet moments to the full dance floor."
    >
        <div class="cta-row">
            <a class="cta" href="{{ route('inquiry.create') }}">Check Availability</a>
            <a class="cta-secondary" href="{{ route('journal.index') }}">Read the Journal</a>
        </div>
    </x-editorial.page-hero>

    <section class="section">
        <div class="page-shell--wide page-stack">
            <p class="editorial-divider">Archive</p>

            @if ($leadStory)
                <div class="portfolio-list">
                    <x-editorial.story-feature class="story-feature--lead" :story="$leadStory" ratio="cinema" show-excerpt />

                    @foreach ($secondaryStories as $story)
                        <x-editorial.story-feature :story="$story" :reverse="$loop->odd" ratio="{{ $loop->even ? 'cinema' : 'landscape' }}" />
                    @endforeach
                </div>
            @else
                <x-editorial.empty-state
                    eyebrow="Portfolio"
                    title="More wedding stories are on the way."
                    copy="You can still check availability or read the journal."
                    :primary-href="route('inquiry.create')"
                    primary-label="Check Availability"
                    :secondary-href="route('journal.index')"
                    secondary-label="Read the Journal"
                />
            @endif

            @if ($stories->hasPages())
                <div class="pagination">
                    {{ $stories->links() }}
                </div>
            @endif
        </div>
    </section>

    <x-editorial.page-closing
        eyebrow="Availability"
        title="Want photos that feel like your day?"
        copy="Send your date, your venue, and a few notes to get started."
        :primary-href="route('inquiry.create')"
        primary-label="Check Availability"
        :secondary-href="route('collections.index')"
        secondary-label="See Collections"
    />
@endsection
