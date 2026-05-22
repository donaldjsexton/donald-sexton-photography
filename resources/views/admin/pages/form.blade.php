@extends('layouts.admin')

@section('title', $page->exists ? 'Edit Page' : 'New Page')
@section('heading', $page->exists ? 'Edit Page' : 'New Page')
@section('content')
    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="admin-form">
        @csrf
        @if ($page->exists)
            @method('PUT')
        @endif

        <div class="field-grid">
            <label>
                Title
                <input type="text" name="title" value="{{ old('title', $page->title) }}" required>
            </label>

            <label>
                Slug
                <input type="text" name="slug" value="{{ old('slug', $page->slug) }}">
            </label>
        </div>

        <div class="field-grid">
            <label>
                Template
                <select name="template">
                    @foreach ($templates as $template)
                        <option value="{{ $template }}" @selected(old('template', $page->template) === $template)>{{ $template }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                Status
                <select name="status">
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(old('status', $page->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <x-admin.media-picker
            name="hero_media_id"
            label="Hero media"
            help-text="Search by filename, alt text, or ID."
            :value="$page->hero_media_id"
            :media="$page->heroMedia"
        />

        <label>
            Publish at
            <input type="datetime-local" name="published_at" value="{{ old('published_at', $page->published_at?->format('Y-m-d\TH:i')) }}">
        </label>

        <label>
            Excerpt
            <textarea name="excerpt" rows="3">{{ old('excerpt', $page->excerpt) }}</textarea>
        </label>

        <label>
            Body
            <textarea name="body" rows="14">{{ old('body', $page->body) }}</textarea>
        </label>

        <div class="field-grid">
            <label>
                SEO title
                <input type="text" name="seo_title" value="{{ old('seo_title', $page->seo_title) }}">
            </label>

            <label>
                Canonical URL
                <input type="url" name="canonical_url" value="{{ old('canonical_url', $page->canonical_url) }}">
            </label>
        </div>

        <label>
            SEO description
            <textarea name="seo_description" rows="3">{{ old('seo_description', $page->seo_description) }}</textarea>
        </label>

        <label>
            Sort order
            <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $page->sort_order ?? 0) }}">
        </label>

        <button class="cta" type="submit" style="border: 0; cursor: pointer;">Save Page</button>
    </form>

    @if ($page->exists)
        @include('admin.blocks.manager', [
            'routePrefix' => 'admin.pages.blocks',
            'ownerInRoute' => true,
            'owner' => $page,
            'blocks' => $page->allBlocks,
            'blockTypes' => $blockTypes,
            'managerTitle' => 'Page Blocks',
        ])
    @endif
@endsection
