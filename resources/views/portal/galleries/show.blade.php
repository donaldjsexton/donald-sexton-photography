@extends('portal.layouts.app')

@section('title', $gallery->title)

@section('content')
    <style>
        .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
        @media (min-width: 640px) { .photo-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); } }
        .photo-tile { position: relative; aspect-ratio: 1 / 1; border-radius: 10px; overflow: hidden; background: #e7d8c5; }
        .photo-tile img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .photo-tile a.dl { position: absolute; right: 8px; bottom: 8px; width: 44px; height: 44px; border-radius: 999px; background: rgba(45,29,21,0.8); color: #fff; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 18px; }
        .gallery-toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; }
        .lock-note { background: #fbf1e2; border: 1px solid #e7d3b6; border-radius: 10px; padding: 12px 14px; color: #6b5446; font-size: 14px; }
    </style>

    <section class="card stack">
        <div class="gallery-toolbar">
            <div>
                <h2 style="margin:0;">{{ $gallery->title }}</h2>
                <a class="meta" href="{{ route('portal.galleries.index') }}">&larr; All galleries</a>
            </div>
            @unless ($downloadsLocked)
                <a class="btn" href="{{ route('portal.galleries.download', $gallery) }}">Download all</a>
            @endunless
        </div>

        @if ($downloadsLocked)
            <div class="lock-note">Full-resolution downloads unlock once your balance is paid in full. You can still preview every photo below.</div>
        @endif

        @forelse ($gallery->albums as $album)
            @if ($album->photos->isNotEmpty())
                <div class="stack">
                    <h3 style="margin:0;">{{ $album->name }}</h3>
                    <div class="photo-grid">
                        @foreach ($album->photos as $photo)
                            <div class="photo-tile">
                                <img src="{{ route('portal.galleries.photo', ['gallery' => $gallery, 'photo' => $photo->uuid]) }}"
                                     alt="{{ $photo->original_name }}" loading="lazy">
                                @unless ($downloadsLocked)
                                    <a class="dl" title="Download"
                                       href="{{ route('portal.galleries.photo.download', ['gallery' => $gallery, 'photo' => $photo->uuid]) }}">&#8595;</a>
                                @endunless
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @empty
            <p class="meta">No photos have been added to this gallery yet.</p>
        @endforelse
    </section>
@endsection
