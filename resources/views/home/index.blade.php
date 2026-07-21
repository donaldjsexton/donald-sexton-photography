@extends('layouts.app')

@section('title', 'Clearwater Wedding Photographer')
@section('meta_description', 'Calm, documentary wedding photography in Clearwater, Clearwater Beach, and Tampa Bay. See real wedding stories, transparent collection pricing, and check your date.')
@section('canonical_url', url()->current())
@section('og_image', $content->leadImage() ?: '')
@section('og_image_alt', 'Donald Sexton Photography')
@section('body_class', 'home-page')

@section('content')
    @if ($homeBlocks->isNotEmpty())
        <x-blocks :blocks="$homeBlocks" />
    @else
        <x-home.hero :content="$content" />
        <x-home.statement :content="$content" />
        <x-home.discover :content="$content" />
        <x-home.portfolio :content="$content" />
        <x-home.journal :content="$content" />
        <x-home.reviews />
        <x-home.inquiry />
    @endif
@endsection
