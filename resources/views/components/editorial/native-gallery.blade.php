@props([
    'items' => collect(),
    'eyebrow' => null,
    'title' => null,
    'copy' => null,
    'altBase' => null,
    'group' => 'gallery',
])

@php
    $items = collect($items)
        ->filter(fn ($media) => filled($media?->path))
        ->values();
@endphp

@if ($items->isNotEmpty())
    <section class="section">
        <div class="page-shell--wide page-stack">
            @if ($eyebrow || $title || $copy)
                <div class="section-header">
                    @if ($eyebrow)
                        <p class="eyebrow">{{ $eyebrow }}</p>
                    @endif
                    @if ($title)
                        <h2 class="section-title">{{ $title }}</h2>
                    @endif
                    @if ($copy)
                        <p class="section-copy">{{ $copy }}</p>
                    @endif
                </div>
            @endif

            <div class="native-gallery-grid">
                @foreach ($items as $media)
                    <a
                        href="{{ $media->publicUrl() }}"
                        class="native-gallery-grid__item"
                        data-lightbox-trigger
                        data-lightbox-group="{{ $group }}"
                        data-lightbox-src="{{ $media->publicUrl() }}"
                        data-lightbox-alt="{{ $altBase ? $altBase.' image '.$loop->iteration : ($media->alt_text ?: '') }}"
                    >
                        <x-editorial.media-frame
                            :media="$media"
                            ratio="natural"
                            :alt="$altBase ? $altBase.' image '.$loop->iteration : null"
                        />
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
