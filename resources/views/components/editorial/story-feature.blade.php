@props([
    'story',
    'reverse' => false,
    'ratio' => 'cinema',
    'showExcerpt' => true,
    'actionLabel' => 'See Story',
])

@php
    $href = route('weddings.show', $story->slug);
    $summary = method_exists($story, 'summaryText')
        ? $story->summaryText(28)
        : ($story->excerpt ?: \Illuminate\Support\Str::words(strip_tags($story->body ?? ''), 28));
    $meta = collect([
        $story->venue?->name ?? $story->location_name,
        $story->event_date?->format('F Y'),
    ])->filter()->implode(' · ');
@endphp

<article data-reveal {{ $attributes->class(['story-feature', 'story-feature--reverse' => $reverse]) }}>
    <a class="story-feature__media" href="{{ $href }}">
        <x-editorial.media-frame
            :media="$story->heroMedia"
            :src="method_exists($story, 'featuredImageUrl') ? $story->featuredImageUrl() : null"
            :ratio="$ratio"
            :alt="$story->title"
        />
    </a>

    <div class="story-feature__copy">
        <p class="eyebrow">{{ $story->story_type_label }}</p>
        <h3 class="feature-title"><a class="story-feature__title-link" href="{{ $href }}">{{ $story->title }}</a></h3>
        @if ($meta)
            <p class="story-feature__meta">{{ $meta }}</p>
        @endif
        @if ($showExcerpt && $summary)
            <p class="story-feature__body">{{ $summary }}</p>
        @endif
        <p class="story-feature__action"><a href="{{ $href }}">{{ $actionLabel }}</a></p>
    </div>
</article>
