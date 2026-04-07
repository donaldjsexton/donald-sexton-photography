@props([
    'media' => null,
    'src' => null,
    'ratio' => 'portrait',
    'label' => null,
    'alt' => null,
    'showPlaceholderText' => false,
    'loading' => 'lazy',
    'decoding' => 'async',
    'fetchpriority' => null,
])

@php
    $url = $src;

    if (! $url && $media?->path) {
        $url = method_exists($media, 'publicUrl')
            ? $media->publicUrl()
            : \Illuminate\Support\Facades\Storage::disk($media->disk ?? 'public')->url($media->path);
    }

    $fallbackLabel = $label ?: ($media?->caption ?: $media?->alt_text ?: 'Editorial Frame');
    $objectPosition = $media && method_exists($media, 'objectPositionValue')
        ? $media->objectPositionValue()
        : '50% 25%';
    $webpUrl = $media && method_exists($media, 'webpPublicUrl')
        ? $media->webpPublicUrl()
        : null;
@endphp

<figure {{ $attributes->class(['media-frame', 'media-frame--'.$ratio]) }}>
    @if ($url)
        @if ($webpUrl)
            <picture>
                <source srcset="{{ $webpUrl }}" type="image/webp">
        @endif
                <img
                    class="media-frame__image"
                    src="{{ $url }}"
                    alt="{{ $alt ?: ($media->alt_text ?: $fallbackLabel) }}"
                    @if ($media?->width) width="{{ $media->width }}" @endif
                    @if ($media?->height) height="{{ $media->height }}" @endif
                    loading="{{ $loading }}"
                    decoding="{{ $decoding }}"
                    @if ($objectPosition) style="object-position: {{ $objectPosition }};" @endif
                    @if ($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif
                >
        @if ($webpUrl)
            </picture>
        @endif
    @else
        <div class="media-frame__placeholder">
            @if ($showPlaceholderText)
                <span>{{ $fallbackLabel }}</span>
            @endif
        </div>
    @endif

    @if ($label && $url)
        <figcaption class="media-frame__caption">{{ $label }}</figcaption>
    @endif
</figure>
