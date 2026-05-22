@props([
    'block',
])

<x-editorial.reading-section>
    @if ($block->heading)
        <h2 class="section-title">{{ $block->heading }}</h2>
    @endif

    @if ($block->body)
        {!! $block->body !!}
    @endif
</x-editorial.reading-section>
