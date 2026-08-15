@props([
    'owner',
    'attachUrl',
    'detachUrlPattern',
    'reorderUrl',
    'heroUrlPattern',
    'pickerUrl',
    'editUrlPattern' => null,
    'title' => 'Photos',
    'helpText' => 'Drag to reorder. Click ★ to promote a photo to hero. Drop photos here to upload straight into the gallery.',
    'pickerTitle' => 'Add photos to this gallery',
])

@php
    $media = $owner->relationLoaded('media') ? $owner->media : $owner->media()->orderBy('mediables.sort_order')->get();
    $heroId = (int) ($owner->hero_media_id ?? 0);
    $updatedAt = $owner->updated_at;
    $maxRequestBytes = \App\Support\UploadRequestBudget::maxRequestBytes();
@endphp

<section
    class="story-gallery"
    data-story-gallery
    data-attach-url="{{ $attachUrl }}"
    data-detach-url-pattern="{{ $detachUrlPattern }}"
    data-reorder-url="{{ $reorderUrl }}"
    data-hero-url-pattern="{{ $heroUrlPattern }}"
    data-picker-url="{{ $pickerUrl }}"
    data-picker-title="{{ $pickerTitle }}"
    data-upload-url="{{ route('admin.media.upload') }}"
    data-max-request-bytes="{{ $maxRequestBytes }}"
    data-hero-input="hero_media_id"
>
    <header class="story-gallery__head">
        <div>
            <p class="eyebrow">Library</p>
            <h3 class="story-gallery__title">{{ $title }}</h3>
            <p class="story-gallery__help">{{ $helpText }}</p>
        </div>

        <div class="story-gallery__stats">
            <span class="story-stat">
                <span class="story-stat__value" data-story-count>{{ $media->count() }}</span>
                <span class="story-stat__label">Photos</span>
            </span>
            <span class="story-stat {{ $heroId ? 'story-stat--ok' : 'story-stat--alert' }}" data-story-hero-stat>
                <span class="story-stat__value">{{ $heroId ? '★' : '○' }}</span>
                <span class="story-stat__label">Hero</span>
            </span>
            <span class="story-stat">
                <span class="story-stat__value">{{ ucfirst($owner->status ?? 'draft') }}</span>
                <span class="story-stat__label">Status</span>
            </span>
            @if ($updatedAt)
                <span class="story-stat">
                    <span class="story-stat__value">{{ $updatedAt->diffForHumans(null, true) }}</span>
                    <span class="story-stat__label">Updated</span>
                </span>
            @endif
        </div>

        <div class="story-gallery__actions">
            <button type="button" class="cta-secondary" data-story-upload>⬆ Upload photos</button>
            <button type="button" class="cta" data-story-add>＋ Add from library</button>

            <input
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                multiple
                hidden
                data-story-file-input
            >
        </div>
    </header>

    <p class="story-gallery__status" role="status" aria-live="polite" data-story-status hidden></p>

    <div class="story-gallery__dropzone" data-story-dropzone hidden aria-hidden="true">
        <span class="story-gallery__dropzone-label">Drop to upload into this gallery</span>
    </div>

    <ol class="story-gallery__grid" data-story-grid @class(['is-empty' => $media->isEmpty()])>
        @foreach ($media as $item)
            @php($isHero = $item->id === $heroId)
            <li class="story-tile {{ $isHero ? 'is-hero' : '' }}" data-story-tile data-media-id="{{ $item->id }}" draggable="true">
                <span class="story-tile__handle" aria-label="Drag to reorder" data-story-handle>⋮⋮</span>

                @if ($isHero)
                    <span class="story-tile__hero-badge" aria-label="Hero photo">★ Hero</span>
                @endif

                <a class="story-tile__photo" href="{{ route('admin.media.edit', $item) }}" tabindex="-1" aria-hidden="true">
                    @if ($item->publicUrl())
                        <img src="{{ $item->publicUrl() }}" alt="{{ $item->alt_text ?: $item->filename }}" loading="lazy">
                    @else
                        <span class="story-tile__placeholder">No preview</span>
                    @endif
                </a>

                <div class="story-tile__actions">
                    @unless ($isHero)
                        <button type="button" class="story-tile__action" data-story-hero title="Make hero">★</button>
                    @endunless
                    <a href="{{ route('admin.media.edit', $item) }}" class="story-tile__action" title="Open in library">↗</a>
                    <button type="button" class="story-tile__action story-tile__action--danger" data-story-remove title="Remove from gallery">✕</button>
                </div>
            </li>
        @endforeach
    </ol>

    <p class="story-gallery__empty" data-story-empty @if ($media->isNotEmpty()) hidden @endif>
        No photos attached yet. Click <strong>Add photos</strong> to pull from the library.
    </p>
</section>
