@extends('layouts.app')

@php
    $bodyHtml = $post->detailBodyHtml();
    $pictime = \App\Support\PicTimeDetailPresentation::for($post, $bodyHtml, 'post');
    $showExternalFallback = $pictime->showExternalFallback();
    $nativeGalleryItems = $pictime->nativeGallery();
    $hasNativeGallery = $pictime->showNativeGallery() && $nativeGalleryItems->isNotEmpty();
    $hasContent = filled($bodyHtml)
        || $hasNativeGallery
        || $pictime->showNarrativeSection()
        || $pictime->showEmbedSection()
        || $showExternalFallback;
    $canonicalForSchema = $post->canonical_url ?: url()->current();
    $featuredImageForSchema = method_exists($post, 'featuredImageUrl') ? $post->featuredImageUrl() : null;
    $articleSchema = \App\Support\StructuredData::journalPost($post);
    $breadcrumbSchema = \App\Support\StructuredData::breadcrumbList([
        ['name' => 'Home', 'url' => route('home')],
        ['name' => 'Journal', 'url' => route('journal.index')],
        ['name' => $post->title, 'url' => $canonicalForSchema],
    ]);
@endphp

@section('title', $post->seo_title ?: $post->title)
@section('meta_description', $post->seo_description ?: $post->excerpt ?: ($showExternalFallback ? $post->externalGallerySummary() : ''))
@section('canonical_url', $post->seoCanonicalUrl() ?: url()->current())
@section('og_type', 'article')
@section('og_image', route('og.journal', $post->slug))
@section('og_image_alt', $post->title)
@section('og_article_published_time', $post->published_at?->toIso8601String() ?: '')
@section('og_article_modified_time', $post->updated_at?->toIso8601String() ?: '')
@section('og_article_author', $post->author_name ?: '')

@push('json_ld')
    <script type="application/ld+json">{!! json_encode($articleSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')

    @php
        $meta = collect([
            $post->published_at?->format('F j, Y'),
            $post->author_name,
        ])->filter()->implode(' · ');
        $nativeGallery = $pictime->nativeGallery();
    @endphp

    <x-editorial.breadcrumbs :items="[
        ['name' => 'Home', 'url' => route('home')],
        ['name' => 'Journal', 'url' => route('journal.index')],
        ['name' => $post->title, 'url' => ''],
    ]" />

    <x-editorial.page-hero
        :eyebrow="$post->post_type_label"
        :title="$post->title"
        :copy="$post->detailHeroCopy()"
        :meta="$meta"
        :media="$pictime->heroMedia()"
        :src="method_exists($post, 'featuredImageUrl') ? $post->featuredImageUrl() : null"
        ratio="portrait"
    />

    @if ($post->hasClientGallery())
        <x-editorial.client-gallery
            :gallery="$post->clientGallery"
            :alt-base="$post->title"
            :group="'post-gallery-'.$post->id"
        />
    @endif

    @if ($pictime->showNativeGallery())
        <x-editorial.native-gallery
            :items="$nativeGallery"
            :alt-base="$post->title"
            :group="'post-'.$post->id"
        />
    @endif

    @if ($bodyHtml)
        <x-editorial.reading-section>
            {!! $bodyHtml !!}
        </x-editorial.reading-section>
    @elseif (! $hasContent)
        <x-editorial.reading-section>
            <p class="empty-post-copy">The full write-up for this post is on its way. In the meantime, take a look at the <a href="{{ route('journal.index') }}">journal archive</a> for other recent stories.</p>
        </x-editorial.reading-section>
    @endif

    @if ($post->blocks->isNotEmpty())
        <x-blocks :blocks="$post->blocks" />
    @endif

    @if ($post->venues->isNotEmpty())
        <section class="section" data-reveal aria-labelledby="post-venues-heading">
            <div class="detail-shell post-venues">
                <p class="editorial-divider" id="post-venues-heading">Venues</p>
                <ul class="post-venues__list">
                    @foreach ($post->venues as $venue)
                        <li>
                            <a class="post-venues__chip" href="{{ route('venues.show', $venue->slug) }}">
                                {{ $venue->name }}
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p class="post-venues__all">
                    <a href="{{ route('venues.index') }}">Browse all venues</a>
                </p>
            </div>
        </section>
    @endif

    <x-editorial.pictime-detail
        :record="$post"
        :presentation="$pictime"
        :body-html="$bodyHtml"
        context="post"
        :return-href="route('journal.index')"
        return-label="Back to Journal"
    />

    @if (! empty($relatedStories) && $relatedStories->isNotEmpty())
        <section class="section" aria-labelledby="related-stories-heading">
            <div class="page-shell--wide page-stack">
                <x-editorial.section-heading
                    eyebrow="Wedding Stories"
                    title="See this venue in action."
                />

                <div class="archive-grid">
                    @foreach ($relatedStories as $related)
                        @php
                            $relatedMeta = collect([
                                $related->venue?->name ?? $related->location_name,
                                $related->event_date?->format('F Y'),
                            ])->filter()->implode(' · ');
                        @endphp
                        <x-editorial.archive-card
                            :title="$related->title"
                            :href="route('weddings.show', $related->slug)"
                            :meta="$relatedMeta ?: null"
                            :copy="method_exists($related, 'summaryText') ? $related->summaryText(24) : $related->excerpt"
                            :media="$related->heroMedia"
                            :media-src="method_exists($related, 'featuredImageUrl') ? $related->featuredImageUrl() : null"
                            :media-alt="$related->title"
                        />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if (! empty($relatedPosts) && $relatedPosts->isNotEmpty())
        <section class="section" aria-labelledby="related-posts-heading">
            <div class="page-shell--wide page-stack">
                <x-editorial.section-heading
                    eyebrow="More from the Journal"
                    title="Other reading you might like."
                />

                <div class="archive-grid">
                    @foreach ($relatedPosts as $related)
                        @php
                            $relatedMeta = collect([
                                $related->post_type_label,
                                $related->published_at?->format('F j, Y'),
                            ])->filter()->implode(' · ');
                        @endphp
                        <x-editorial.archive-card
                            :title="$related->title"
                            :href="route('journal.show', $related->slug)"
                            :meta="$relatedMeta ?: null"
                            :copy="method_exists($related, 'summaryText') ? $related->summaryText(24) : $related->excerpt"
                            :media="$related->heroMedia"
                            :media-src="method_exists($related, 'featuredImageUrl') ? $related->featuredImageUrl() : null"
                            :media-alt="$related->title"
                        />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <x-editorial.page-closing
        eyebrow="Availability"
        title="Ready to talk about your day?"
        copy="If this feels like the right fit, send your date and venue."
        :primary-href="route('inquiry.create')"
        primary-label="Check Availability"
        :secondary-href="route('journal.index')"
        secondary-label="Back to Journal"
    />
@endsection
