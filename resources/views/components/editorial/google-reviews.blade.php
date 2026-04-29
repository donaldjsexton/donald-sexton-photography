@props([
    'variant' => 'section',
    'eyebrow' => 'On Google',
    'title' => 'What couples are saying.',
    'limit' => 3,
])

@php
    $snapshot = app(\App\Services\GoogleBusinessProfile::class)->snapshot();
    $rating = $snapshot ? round((float) ($snapshot['rating'] ?? 0), 1) : null;
    $reviewCount = $snapshot ? (int) ($snapshot['reviewCount'] ?? 0) : 0;
    $recent = collect($snapshot['recentReviews'] ?? [])
        ->filter(fn ($r) => filled($r['excerpt'] ?? null) && (int) ($r['rating'] ?? 0) > 0)
        ->take($limit)
        ->values();
    $listingUrl = app(\App\Services\GoogleBusinessProfile::class)->publicListingUrl();
    $blockTag = $variant === 'aside' ? 'aside' : 'section';
    $blockClass = $variant === 'aside'
        ? 'google-reviews google-reviews--aside'
        : 'google-reviews section';
@endphp

@if ($snapshot && $rating > 0 && $reviewCount > 0)
    <{{ $blockTag }} {{ $attributes->class([$blockClass]) }} data-reveal>
        <div @class([
            'page-shell--tight google-reviews__inner' => $variant !== 'aside',
            'google-reviews__inner' => $variant === 'aside',
        ])>
            @if ($variant !== 'aside')
                <p class="eyebrow">{{ $eyebrow }}</p>
                <h2 class="section-title google-reviews__title">{{ $title }}</h2>
            @endif

            <div class="google-reviews__summary">
                <span class="google-reviews__rating" aria-label="{{ $rating }} out of 5 stars">
                    <span class="google-reviews__stars" aria-hidden="true">
                        @for ($i = 1; $i <= 5; $i++)
                            <span @class([
                                'google-reviews__star',
                                'is-filled' => $i <= floor($rating),
                                'is-half' => $i - 0.5 <= $rating && $i > floor($rating),
                            ])>★</span>
                        @endfor
                    </span>
                    <span class="google-reviews__rating-value">{{ number_format($rating, 1) }}</span>
                </span>
                <span class="google-reviews__count">{{ $reviewCount }} {{ \Illuminate\Support\Str::plural('review', $reviewCount) }} on Google</span>
                @if ($listingUrl)
                    <a class="google-reviews__link" href="{{ $listingUrl }}" target="_blank" rel="noopener noreferrer">Read on Google</a>
                @endif
            </div>

            @if ($recent->isNotEmpty())
                <ul class="google-reviews__list">
                    @foreach ($recent as $review)
                        <li class="google-reviews__card">
                            <p class="google-reviews__card-stars" aria-label="{{ $review['rating'] }} out of 5 stars">
                                @for ($i = 1; $i <= 5; $i++)
                                    <span @class([
                                        'google-reviews__star',
                                        'is-filled' => $i <= (int) $review['rating'],
                                    ]) aria-hidden="true">★</span>
                                @endfor
                            </p>
                            <blockquote class="google-reviews__card-quote">{{ $review['excerpt'] }}</blockquote>
                            <p class="google-reviews__card-author">
                                <span>{{ $review['author'] }}</span>
                                @if (! empty($review['date']))
                                    <span class="google-reviews__card-date">{{ $review['date'] }}</span>
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </{{ $blockTag }}>
@endif
