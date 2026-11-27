@extends('layouts.admin-v2')

@section('title', $media->exists ? 'Edit Media' : 'Upload Media')
@section('eyebrow', 'Photo library')
@section('heading', $media->exists ? 'Edit Media' : 'Upload Media')
@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
    @endphp
    @php
        $url = $media->publicUrl();
        $focalX = old('focal_point_x', $media->focal_point_x ?? 0.5);
        $focalY = old('focal_point_y', $media->focal_point_y ?? 0.25);
    @endphp

    @if ($errors->any())
        <ul class="mb-4 list-disc rounded-xl border border-rose-200 bg-rose-50 px-8 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ $media->exists ? route('admin.media.update', $media) : route('admin.media.store') }}" enctype="multipart/form-data" class="max-w-none! space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800/50">
        @csrf
        @if ($media->exists)
            @method('PUT')
        @endif

        @if ($url)
            <div
                class="relative max-w-xl cursor-crosshair overflow-hidden rounded-2xl shadow-md [--focal-x:50%] [--focal-y:50%] before:pointer-events-none before:absolute before:inset-y-0 before:left-[var(--focal-x)] before:w-px before:-translate-x-1/2 before:bg-white/80 before:content-[''] after:pointer-events-none after:absolute after:inset-x-0 after:top-[var(--focal-y)] after:h-px after:-translate-y-1/2 after:bg-white/80 after:content-['']"
                data-focal-picker
                data-focal-x="{{ $focalX }}"
                data-focal-y="{{ $focalY }}"
            >
                <img
                    class="block h-auto w-full"
                    src="{{ $url }}"
                    alt="{{ $media->alt_text ?: $media->filename }}"
                    data-focal-preview
                    style="object-position: {{ $media->objectPositionValue() }};"
                >
                <button class="pointer-events-none absolute top-[var(--focal-y)] left-[var(--focal-x)] size-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-gray-900/90 ring-1 ring-gray-900/20" type="button" aria-label="Adjust image focus" data-focal-handle></button>
            </div>
        @endif

        <x-ui.field label="Image file">
            <input class="{{ $input }}" type="file" name="file" {{ $media->exists ? '' : 'required' }}>
        </x-ui.field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Alt text">
                <input class="{{ $input }}" type="text" name="alt_text" value="{{ old('alt_text', $media->alt_text) }}">
            </x-ui.field>

            <x-ui.field label="Credit">
                <input class="{{ $input }}" type="text" name="credit" value="{{ old('credit', $media->credit) }}">
            </x-ui.field>
        </div>

        <x-ui.field label="Caption">
            <textarea class="{{ $input }}" name="caption" rows="4">{{ old('caption', $media->caption) }}</textarea>
        </x-ui.field>

        <section class="space-y-4">
            <div>
                <strong class="text-sm font-semibold text-gray-900 dark:text-white">Image focus</strong>
                <p class="text-sm text-gray-500 dark:text-gray-400">Click or drag on the preview to set the crop focus used across the site.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Focus X">
                    <input class="{{ $input }}" type="number" name="focal_point_x" min="0" max="1" step="0.0001" value="{{ $focalX }}" data-focal-x-input>
                </x-ui.field>

                <x-ui.field label="Focus Y">
                    <input class="{{ $input }}" type="number" name="focal_point_y" min="0" max="1" step="0.0001" value="{{ $focalY }}" data-focal-y-input>
                </x-ui.field>
            </div>
        </section>

        <x-ui.button type="submit">{{ $media->exists ? 'Save Media' : 'Upload Media' }}</x-ui.button>
    </form>
@endsection
