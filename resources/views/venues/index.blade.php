@extends('layouts.app')

@section('title', 'Venues')

@section('content')
    <x-editorial.page-hero
        class="page-hero--archive-intro"
        eyebrow="Venues"
        title="Wedding venues, with real stories to match."
        copy="See places I have photographed and the stories tied to them."
        shell="tight"
    />

    <section class="section">
        <div class="page-shell--wide page-stack page-stack--compact">
            <p class="editorial-divider">Directory</p>

            @if ($venues->isNotEmpty())
                <div class="archive-grid">
                    @foreach ($venues as $venue)
                        <x-editorial.archive-card
                            :title="$venue->name"
                            :href="route('venues.show', $venue->slug)"
                            :meta="collect([$venue->city, $venue->state])->filter()->implode(', ')"
                            :copy="$venue->summary"
                            :media="$venue->heroMedia"
                            :media-alt="$venue->name"
                            :media-loading="$loop->first ? 'eager' : 'lazy'"
                        >
                            <p class="meta">{{ $venue->wedding_stories_count }} stories · {{ $venue->journal_posts_count }} journal posts</p>
                        </x-editorial.archive-card>
                    @endforeach
                </div>
            @else
                <x-editorial.empty-state
                    eyebrow="Venues"
                    title="Your venue does not need to be listed here yet."
                    copy="You can still send your date and venue and get started."
                    :primary-href="route('inquiry.create')"
                    primary-label="Check Availability"
                    :secondary-href="route('weddings.index')"
                    secondary-label="See Wedding Stories"
                />
            @endif

            <div class="pagination">
                {{ $venues->links() }}
            </div>
        </div>
    </section>

    <x-editorial.page-closing
        eyebrow="Availability"
        title="Have a venue in mind?"
        copy="Send your date and venue, even if it is not listed here yet."
        :primary-href="route('inquiry.create')"
        primary-label="Check Availability"
        :secondary-href="route('weddings.index')"
        secondary-label="See Wedding Stories"
    />
@endsection
