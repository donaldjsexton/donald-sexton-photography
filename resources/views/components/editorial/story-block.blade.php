@props([
    'block',
])

@php
    $blockType = $block->block_type;
    $settings = $block->settings_json ?? [];
@endphp

@if ($blockType === 'quote')
    <section class="story-block story-block--quote">
        @if ($block->heading)
            <p class="eyebrow">{{ $block->heading }}</p>
        @endif
        @if ($block->body)
            <div class="story-block__body">“{{ $block->body }}”</div>
        @endif
    </section>
@elseif ($blockType === 'image_pair')
    <section class="story-block">
        @if ($block->heading)
            <p class="eyebrow">{{ $block->heading }}</p>
        @endif
        <div class="grid-two">
            <x-editorial.media-frame ratio="portrait" :label="$settings['left_label'] ?? 'Image Pair'" />
            <x-editorial.media-frame ratio="portrait" :label="$settings['right_label'] ?? 'Image Pair'" />
        </div>
        @if ($block->body)
            <div class="story-block__body">{{ $block->body }}</div>
        @endif
    </section>
@elseif ($blockType === 'full_bleed_image')
    <section class="story-block">
        <x-editorial.media-frame ratio="cinema" :label="$block->heading ?: 'Full Bleed'" />
        @if ($block->body)
            <div class="story-block__body">{{ $block->body }}</div>
        @endif
    </section>
@elseif ($blockType === 'carousel')
    <section class="story-block">
        @if ($block->heading)
            <p class="eyebrow">{{ $block->heading }}</p>
        @endif
        <div class="grid-two">
            <x-editorial.media-frame ratio="landscape" :label="$settings['frame_one_label'] ?? 'Carousel Frame'" />
            <x-editorial.media-frame ratio="landscape" :label="$settings['frame_two_label'] ?? 'Carousel Frame'" />
        </div>
        @if ($block->body)
            <div class="story-block__body">{{ $block->body }}</div>
        @endif
    </section>
@elseif ($blockType === 'spacer')
    <section class="story-block" aria-hidden="true"></section>
@else
    <section class="story-block">
        @if ($block->heading)
            <p class="eyebrow">{{ $block->heading }}</p>
        @endif
        @if ($block->body)
            <div class="story-block__body">{{ $block->body }}</div>
        @endif
    </section>
@endif
