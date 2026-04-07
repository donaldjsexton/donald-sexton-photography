@extends('layouts.admin')

@section('title', 'Media')
@section('heading', 'Media Library')
@section('content')
    <div class="admin-toolbar">
        <p class="section-copy">Upload and annotate the images used across hero areas, stories, pages, and imported content.</p>
        <a class="cta" href="{{ route('admin.media.create') }}">Upload Media</a>
    </div>

    <section class="admin-media-grid">
        @foreach ($mediaItems as $media)
            @php($url = $media->publicUrl())
            <article class="admin-card admin-media-card">
                @if ($url)
                    <img src="{{ $url }}" alt="{{ $media->alt_text ?: $media->filename }}" loading="lazy">
                @endif
                <div class="admin-media-card__body">
                    <strong>{{ $media->filename }}</strong>
                    <p class="meta">#{{ $media->id }} · {{ strtoupper($media->disk ?? 'public') }}</p>
                    <a class="cta-secondary" href="{{ route('admin.media.edit', $media) }}">Edit</a>
                </div>
            </article>
        @endforeach
    </section>

    <div class="pagination">
        {{ $mediaItems->links() }}
    </div>
@endsection
