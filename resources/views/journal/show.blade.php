@extends('layouts.app')

@php
    $bodyHtml = $post->detailBodyHtml();
    $pictime = \App\Support\PicTimeDetailPresentation::for($post, $bodyHtml, 'post');
    $showExternalFallback = $pictime->showExternalFallback();
@endphp

@section('title', $post->seo_title ?: $post->title)
@section('meta_description', $post->seo_description ?: $post->excerpt ?: ($showExternalFallback ? $post->externalGallerySummary() : ''))
@section('canonical_url', $post->canonical_url ?: url()->current())

@section('content')
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
