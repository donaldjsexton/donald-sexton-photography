@props([
    'gallery' => null,
    'eyebrow' => null,
    'title' => null,
    'copy' => null,
    'altBase' => null,
    'group' => 'client-gallery',
])

@php
    $photos = $gallery ? $gallery->orderedPhotos() : collect();
@endphp

@if ($photos->isNotEmpty())
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
                @foreach ($photos as $photo)
                    @php
                        $src = route('galleries.embed.photo', ['gallery' => $gallery, 'photo' => $photo->uuid]);
                        $alt = $photo->original_name ?: ($altBase ? $altBase.' — photo '.$loop->iteration : '');
                    @endphp
                    <a
                        href="{{ $src }}"
                        class="native-gallery-grid__item"
                        data-lightbox-trigger
                        data-lightbox-group="{{ $group }}"
                        data-lightbox-src="{{ $src }}"
                        data-lightbox-alt="{{ $alt }}"
                    >
                        <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" decoding="async">
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
