@extends('layouts.app')

@section('title', $page->seo_title ?: $page->title)
@section('meta_description', $page->seo_description ?: $page->excerpt ?: '')
@section('canonical_url', $page->canonical_url ?: url()->current())

@section('content')
    <x-editorial.page-hero
        :eyebrow="$eyebrow ?? null"
        :title="$page->title"
        :copy="$page->excerpt"
        :media="$page->heroMedia"
        ratio="portrait"
    />

    @if ($page->blocks->isNotEmpty())
        <x-blocks :blocks="$page->blocks" />
    @elseif ($page->body)
        <x-editorial.reading-section>
            {!! $page->body !!}
        </x-editorial.reading-section>
    @endif

    @unless ($page->blocks->contains(fn ($block) => $block->type === 'cta'))
        <x-editorial.page-closing
            eyebrow="Next Step"
            title="Want to keep going?"
            copy="You can keep looking through the work, or you can send your date and venue."
            :primary-href="route('inquiry.create')"
            primary-label="Check Availability"
            :secondary-href="route('weddings.index')"
            secondary-label="See Wedding Stories"
        />
    @endunless
@endsection
