@extends('layouts.admin')

@section('title', $media->exists ? 'Edit Media' : 'Upload Media')
@section('heading', $media->exists ? 'Edit Media' : 'Upload Media')
@section('content')
    @php
        $url = $media->publicUrl();
        $focalX = old('focal_point_x', $media->focal_point_x ?? 0.5);
        $focalY = old('focal_point_y', $media->focal_point_y ?? 0.25);
    @endphp

    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ $media->exists ? route('admin.media.update', $media) : route('admin.media.store') }}" enctype="multipart/form-data" class="admin-form">
        @csrf
        @if ($media->exists)
            @method('PUT')
        @endif

        @if ($url)
            <div
                class="admin-preview admin-focal-picker"
                data-focal-picker
                data-focal-x="{{ $focalX }}"
                data-focal-y="{{ $focalY }}"
            >
                <img
                    src="{{ $url }}"
                    alt="{{ $media->alt_text ?: $media->filename }}"
                    data-focal-preview
                    style="object-position: {{ $media->objectPositionValue() }};"
                >
                <button class="admin-focal-picker__handle" type="button" aria-label="Adjust image focus" data-focal-handle></button>
            </div>
        @endif

        <label>
            Image file
            <input type="file" name="file" {{ $media->exists ? '' : 'required' }}>
        </label>

        <div class="field-grid">
            <label>
                Alt text
                <input type="text" name="alt_text" value="{{ old('alt_text', $media->alt_text) }}">
            </label>

            <label>
                Credit
                <input type="text" name="credit" value="{{ old('credit', $media->credit) }}">
            </label>
        </div>

        <label>
            Caption
            <textarea name="caption" rows="4">{{ old('caption', $media->caption) }}</textarea>
        </label>

        <section class="admin-focal-fields">
            <div class="admin-focal-fields__copy">
                <strong>Image focus</strong>
                <p class="meta">Click or drag on the preview to set the crop focus used across the site.</p>
            </div>

            <div class="field-grid">
                <label>
                    Focus X
                    <input type="number" name="focal_point_x" min="0" max="1" step="0.0001" value="{{ $focalX }}" data-focal-x-input>
                </label>

                <label>
                    Focus Y
                    <input type="number" name="focal_point_y" min="0" max="1" step="0.0001" value="{{ $focalY }}" data-focal-y-input>
                </label>
            </div>
        </section>

        <button class="cta" type="submit" style="border: 0; cursor: pointer;">{{ $media->exists ? 'Save Media' : 'Upload Media' }}</button>
    </form>
@endsection
