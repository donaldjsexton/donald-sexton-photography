@extends('layouts.app')

@php
    $bodyHtml = $story->detailBodyHtml();
    $presentation = $story->presentationContent();
    $pictime = \App\Support\PicTimeDetailPresentation::for($story, $bodyHtml, 'story');
    $showExternalFallback = $pictime->showExternalFallback();
    $storyFeaturedImage = $story->featuredImageUrl();
@endphp

@section('title', $story->seo_title ?: $story->title)
@section('meta_description', $story->seo_description ?: $presentation['hero_copy'] ?: ($showExternalFallback ? $story->externalGallerySummary() : ''))
@section('canonical_url', $story->canonical_url ?: url()->current())
@section('og_type', 'article')
@section('og_image', $storyFeaturedImage ?: '')
@section('og_image_alt', $story->title)
@section('og_article_published_time', $story->published_at?->toIso8601String() ?: '')
@section('og_article_modified_time', $story->updated_at?->toIso8601String() ?: '')

@push('json_ld')
    @if ($story->venue)
        <script type="application/ld+json">{!! json_encode(\App\Support\StructuredData::place($story->venue->loadMissing('heroMedia')), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
    @php
        $schemaGalleryMedia = \App\Support\StructuredData::galleryMediaForStory($story, $pictime);
    @endphp
    <script type="application/ld+json">{!! json_encode(\App\Support\StructuredData::weddingStory($story, $schemaGalleryMedia), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
    @php
        $heroMedia = $pictime->heroMedia();
        $nativeGallery = $pictime->nativeGallery();
        $meta = collect([
            $story->venue?->name ?? $story->location_name,
            $story->event_date?->format('F j, Y'),
        ])->filter()->implode(' · ');
    @endphp

    <x-editorial.page-hero
        :eyebrow="$story->story_type_label"
        :title="$story->title"
        :copy="$story->detailHeroCopy()"
        :meta="$meta"
        :media="$heroMedia"
        :src="$story->featuredImageUrl()"
        ratio="portrait"
    />

    @if ($pictime->showNativeGallery())
        <x-editorial.native-gallery
            :items="$nativeGallery"
            :alt-base="$story->title"
            :group="'story-'.$story->id"
        />
    @endif

    @if ($pictime->showImportedGallery($presentation['gallery_html']))
        <section class="section">
            <div class="page-shell--wide">
                <div class="imported-story-gallery rich-text" data-reveal>
                    {!! $presentation['gallery_html'] !!}
                </div>
            </div>
        </section>
    @endif

    @if ($bodyHtml)
        <x-editorial.reading-section>
            {!! $bodyHtml !!}
        </x-editorial.reading-section>
    @endif

    <x-editorial.pictime-detail
        :record="$story"
        :presentation="$pictime"
        :body-html="$bodyHtml"
        context="story"
        :return-href="route('weddings.index')"
        return-label="Back to Weddings"
    />

    @if ($story->storyBlocks->isNotEmpty())
        <section class="section">
            <div class="page-shell--wide page-stack">
                <x-editorial.section-heading
                    eyebrow="Story Sequence"
                    title="More from this wedding day."
                />

                @foreach ($story->storyBlocks as $block)
                    <x-editorial.story-block :block="$block" />
                @endforeach
            </div>
        </section>
    @endif

    <x-editorial.page-closing
        eyebrow="Inquiry"
        title="Planning your own wedding?"
        copy="If this feels right, send your date and venue and I will guide you from there."
        :primary-href="route('inquiry.create')"
        primary-label="Check Availability"
        :secondary-href="route('weddings.index')"
        secondary-label="Back to Weddings"
    />
@endsection
