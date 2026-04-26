@extends('layouts.app')

@section('title', $venue->seo_title ?: $venue->name)
@section('meta_description', $venue->seo_description ?: $venue->summary ?: '')
@section('canonical_url', url()->current())
@section('og_image', $venue->heroMedia?->publicUrl() ?: '')
@section('og_image_alt', $venue->name)

@push('json_ld')
    <script type="application/ld+json">{!! json_encode(\App\Support\StructuredData::place($venue), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
    @php
        $hasStories = $stories->isNotEmpty();
        $hasPosts = $posts->isNotEmpty();
    @endphp

    <x-editorial.page-hero
        eyebrow="Venue"
        :title="$venue->name"
        :copy="$venue->summary"
        :media="$venue->heroMedia"
        ratio="portrait"
    />

    @if ($venue->body)
        <x-editorial.reading-section>
            {!! $venue->body !!}
        </x-editorial.reading-section>
    @endif

    @if ($hasStories)
        <section class="section">
            <div class="page-shell--wide page-stack">
                <x-editorial.section-heading
                    eyebrow="Related Stories"
                    title="Wedding stories at this venue."
                />

                <div class="archive-grid">
                    @foreach ($stories as $story)
                        <x-editorial.archive-card
                            :title="$story->title"
                            :href="route('weddings.show', $story->slug)"
                            :copy="method_exists($story, 'summaryText') ? $story->summaryText(24) : $story->excerpt"
                        />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($hasPosts)
        <section class="section">
            <div class="page-shell--wide page-stack">
                <x-editorial.section-heading
                    eyebrow="Related Journal Posts"
                    title="Planning notes and stories tied to this venue."
                />

                <div class="archive-grid">
                    @foreach ($posts as $post)
                        <x-editorial.archive-card
                            :title="$post->title"
                            :href="route('journal.show', $post->slug)"
                            :copy="method_exists($post, 'summaryText') ? $post->summaryText(24) : $post->excerpt"
                        />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @unless ($hasStories || $hasPosts || $venue->body)
        <section class="section">
            <div class="page-shell--tight">
                <x-editorial.empty-state
                    eyebrow="Continue"
                    title="You can still start with this venue."
                    copy="Even if more stories are not published yet, this venue can guide the first conversation."
                    :primary-href="route('inquiry.create')"
                    primary-label="Check Availability"
                    :secondary-href="route('weddings.index')"
                    secondary-label="See Wedding Stories"
                />
            </div>
        </section>
    @endunless

    <x-editorial.page-closing
        eyebrow="Inquiry"
        title="Planning around this venue?"
        copy="Send your date and venue so I can guide you to the right fit."
        :primary-href="route('inquiry.create')"
        primary-label="Check Availability"
        :secondary-href="route('venues.index')"
        secondary-label="More Venues"
    />
@endsection
