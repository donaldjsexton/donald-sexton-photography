@props(['block'])
@php $content = app(\App\Support\HomeContent::class); @endphp
<x-home.hero :content="$content" :eyebrow="$block->heading ?: null" :intro="$block->body ?: null" />
