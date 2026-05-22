@props([
    'block',
])

<section data-reveal class="section">
    <div class="page-shell--wide">
        <x-editorial.media-frame
            :media="$block->media->first()"
            ratio="cinema"
            :alt="$block->heading"
            :label="$block->heading"
        />
    </div>

    @if ($block->body)
        <x-editorial.reading-section>
            {!! $block->body !!}
        </x-editorial.reading-section>
    @endif
</section>
