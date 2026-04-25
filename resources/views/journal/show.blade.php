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
    $articleSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $post->title,
        'description' => $post->seo_description ?: $post->excerpt ?: $post->summaryText(40),
        'datePublished' => $post->published_at?->toIso8601String(),
        'dateModified' => $post->updated_at?->toIso8601String(),
        'image' => $featuredImageForSchema,
        'author' => filled($post->author_name) ? [
            '@type' => 'Person',
            'name' => $post->author_name,
        ] : null,
        'publisher' => [
            '@type' => 'Organization',
            'name' => config('app.name', 'Donald Sexton Photography'),
            'url' => rtrim(config('app.url', url('/')), '/'),
        ],
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $canonicalForSchema,
        ],
    ], fn ($value) => $value !== null && $value !== '');
@endphp

@section('title', $post->seo_title ?: $post->title)
@section('meta_description', $post->seo_description ?: $post->excerpt ?: ($showExternalFallback ? $post->externalGallerySummary() : ''))
@section('canonical_url', $post->canonical_url ?: url()->current())
@section('og_type', 'article')
@section('og_image', $featuredImageForSchema ?: '')
@section('og_image_alt', $post->title)
@section('og_article_published_time', $post->published_at?->toIso8601String() ?: '')
@section('og_article_modified_time', $post->updated_at?->toIso8601String() ?: '')
@section('og_article_author', $post->author_name ?: '')

@section('content')
    <script type="application/ld+json">{!! json_encode($articleSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    @php
        $meta = collect([
            $post->published_at?->format('F j, Y'),
            $post->author_name,
        ])->filter()->implode(' · ');
        $nativeGallery = $pictime->nativeGallery();
    @endphp

    <x-editorial.page-hero
        :eyebrow="$post->post_type_label"
        :title="$post->title"
        :copy="$post->detailHeroCopy()"
        :meta="$meta"
        :media="$pictime->heroMedia()"
        :src="method_exists($post, 'featuredImageUrl') ? $post->featuredImageUrl() : null"
        ratio="portrait"
    />

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

    <x-editorial.pictime-detail
        :record="$post"
        :presentation="$pictime"
        :body-html="$bodyHtml"
        context="post"
        :return-href="route('journal.index')"
        return-label="Back to Journal"
    />

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
