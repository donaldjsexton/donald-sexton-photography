@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-editorial.page-hero
        class="page-hero--archive-intro"
        eyebrow="Journal"
        :title="$title"
        :copy="$description ?: 'Wedding stories, venue notes, and easy planning help in one place.'"
        ratio="portrait"
    />

    <section class="section">
        <div class="page-shell--wide page-stack page-stack--compact">
            <p class="editorial-divider">Archive</p>

            @if ($posts->isNotEmpty())
                <div class="journal-list">
                    @foreach ($posts as $post)
                        @php
                            $thumb = $post->featuredImageUrl();
                            $meta = $post->post_type_label.($post->published_at ? ' · '.$post->published_at->format('F j, Y') : '');
                        @endphp
                        <article data-reveal class="journal-entry">
                            <div class="journal-entry__content">
                                <p class="journal-entry__meta">{{ $meta }}</p>
                                <h2 class="journal-entry__title">
                                    <a href="{{ route('journal.show', $post->slug) }}">{{ $post->title }}</a>
                                </h2>
                                @if ($post->summaryText(28))
                                    <p class="journal-entry__copy">{{ $post->summaryText(28) }}</p>
                                @endif
                                <a class="journal-entry__link" href="{{ route('journal.show', $post->slug) }}">Read More</a>
                            </div>

                            @if ($thumb)
                                <a href="{{ route('journal.show', $post->slug) }}" class="journal-entry__media">
                                    <x-editorial.media-frame
                                        :media="$post->heroMedia"
                                        :src="$thumb"
                                        :alt="$post->title"
                                        ratio="landscape"
                                        class="media-frame--clean"
                                        :loading="$loop->first ? 'eager' : 'lazy'"
                                        :fetchpriority="$loop->first ? 'high' : null"
                                    />
                                </a>
                            @endif
                        </article>
                    @endforeach
                </div>
            @else
                <x-editorial.empty-state
                    eyebrow="Journal"
                    title="New posts are on the way."
                    copy="For now, start with the wedding stories or check availability."
                    :primary-href="route('weddings.index')"
                    primary-label="See Wedding Stories"
                    :secondary-href="route('inquiry.create')"
                    secondary-label="Check Availability"
                />
            @endif

            <div class="pagination">
                {{ $posts->links() }}
            </div>
        </div>
    </section>

    <x-editorial.page-closing
        eyebrow="Next Step"
        title="Ready to talk about your day?"
        copy="If the work feels right, send your date and venue."
        :primary-href="route('inquiry.create')"
        primary-label="Check Availability"
        :secondary-href="route('weddings.index')"
        secondary-label="See Wedding Stories"
    />
@endsection
