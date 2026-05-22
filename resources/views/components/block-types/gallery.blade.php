@props([
    'block',
])

<section data-reveal class="section">
    <div class="page-shell--wide page-stack">
        @if ($block->heading || $block->body)
            <x-editorial.section-heading
                :title="$block->heading"
                :copy="$block->body"
            />
        @endif

        @if ($block->media->isNotEmpty())
            <div class="archive-grid">
                @foreach ($block->media as $media)
                    <x-editorial.media-frame
                        :media="$media"
                        ratio="portrait"
                        :alt="$media->alt_text ?: $block->heading"
                    />
                @endforeach
            </div>
        @endif
    </div>
</section>
