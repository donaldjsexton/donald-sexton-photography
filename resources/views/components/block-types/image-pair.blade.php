@props([
    'block',
])

@php
    $items = $block->media;
@endphp

<section data-reveal class="section">
    <div class="page-shell--wide page-stack">
        @if ($block->heading)
            <x-editorial.section-heading :title="$block->heading" />
        @endif

        <div class="grid-two">
            <x-editorial.media-frame :media="$items->get(0)" ratio="portrait" />
            <x-editorial.media-frame :media="$items->get(1)" ratio="portrait" />
        </div>

        @if ($block->body)
            <div class="detail-shell rich-text">
                {!! $block->body !!}
            </div>
        @endif
    </div>
</section>
