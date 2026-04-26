@props([
    'name',
    'label' => 'Media',
    'value' => null,
    'media' => null,
    'helpText' => null,
    'required' => false,
])

@php
    $currentValue = old($name, $value);
    $currentMedia = $media instanceof \App\Models\Media ? $media : null;
    $previewUrl = $currentMedia?->publicUrl();
    $previewAlt = $currentMedia?->alt_text ?: $currentMedia?->filename;
    $fieldId = 'media-picker-'.$name.'-'.\Illuminate\Support\Str::random(6);
@endphp

<div
    class="media-picker"
    data-media-picker
    data-media-picker-endpoint="{{ route('admin.media.picker') }}"
    data-media-picker-input="#{{ $fieldId }}"
>
    <input
        type="hidden"
        id="{{ $fieldId }}"
        name="{{ $name }}"
        value="{{ $currentValue }}"
        @if ($required) required @endif
        data-media-picker-value
    >

    <div class="media-picker__label">
        <span>{{ $label }}</span>
        @if ($helpText)
            <span class="meta">{{ $helpText }}</span>
        @endif
    </div>

    <div class="media-picker__surface" data-media-picker-surface @class(['is-empty' => ! $previewUrl])>
        <div class="media-picker__preview" data-media-picker-preview>
            @if ($previewUrl)
                <img src="{{ $previewUrl }}" alt="{{ $previewAlt }}" loading="lazy" data-media-picker-preview-image>
            @else
                <span class="media-picker__placeholder" data-media-picker-placeholder>No image selected</span>
            @endif
        </div>

        <div class="media-picker__meta">
            <strong data-media-picker-filename>{{ $currentMedia?->filename ?: '—' }}</strong>
            <span class="meta" data-media-picker-id>@if ($currentMedia) #{{ $currentMedia->id }} @endif</span>
        </div>

        <div class="media-picker__actions">
            <button type="button" class="cta-secondary" data-media-picker-open>Choose image</button>
            <button
                type="button"
                class="cta-secondary media-picker__clear"
                data-media-picker-clear
                @if (! $currentValue) hidden @endif
            >Clear</button>
        </div>
    </div>
</div>
