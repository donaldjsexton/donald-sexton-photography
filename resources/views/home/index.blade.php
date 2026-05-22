@extends('layouts.app')

@section('title', 'Donald Sexton Photography')
@section('meta_description', 'Calm wedding photography for Clearwater, Tampa, and beyond. Real wedding stories, planning guidance, and straightforward next steps.')
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
