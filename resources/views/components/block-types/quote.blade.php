@props([
    'block',
])

<section data-reveal class="section">
    <div class="detail-shell">
        <blockquote class="pull-quote">
            @if ($block->body)
                <p class="pull-quote__text">&ldquo;{{ $block->body }}&rdquo;</p>
            @endif

            @if ($block->heading)
                <cite class="pull-quote__cite">{{ $block->heading }}</cite>
            @endif
        </blockquote>
    </div>
</section>
