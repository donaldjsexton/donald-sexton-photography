@extends('portal.layouts.app')

@section('title', 'Galleries')

@section('content')
    <style>
        .gallery-cards { display: grid; grid-template-columns: 1fr; gap: 14px; }
        @media (min-width: 560px) { .gallery-cards { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 900px) { .gallery-cards { grid-template-columns: repeat(3, 1fr); } }
        .gallery-card { display: block; text-decoration: none; color: inherit; border: 1px solid #e7d8c5; border-radius: 12px; overflow: hidden; background: #fff; }
        .gallery-card__cover { aspect-ratio: 3 / 2; background: #e7d8c5; object-fit: cover; width: 100%; display: block; }
        .gallery-card__body { padding: 12px 14px; }
        .gallery-card__body h3 { margin: 0 0 4px; font-size: 16px; }
    </style>

    <section class="card stack">
        <div>
            <h2>Your galleries</h2>
            <p class="meta" style="margin:0;">View and download the photos from your sessions.</p>
        </div>

        @if ($galleries->isEmpty())
            <p class="meta">No galleries have been shared with you yet.</p>
        @else
            <div class="gallery-cards">
                @foreach ($galleries as $gallery)
                    <a class="gallery-card" href="{{ route('portal.galleries.show', $gallery) }}">
                        @if ($gallery->coverPhoto)
                            <img class="gallery-card__cover"
                                 src="{{ route('portal.galleries.photo', ['gallery' => $gallery, 'photo' => $gallery->coverPhoto->uuid]) }}"
                                 alt="{{ $gallery->title }}" loading="lazy">
                        @else
                            <span class="gallery-card__cover"></span>
                        @endif
                        <div class="gallery-card__body">
                            <h3>{{ $gallery->title }}</h3>
                            <span class="meta">{{ trans_choice(':count album|:count albums', $gallery->albums_count, ['count' => $gallery->albums_count]) }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
@endsection
