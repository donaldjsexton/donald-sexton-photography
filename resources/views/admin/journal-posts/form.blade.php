@extends('layouts.admin')

@section('title', $post->exists ? 'Edit Journal Post' : 'New Journal Post')
@section('heading', $post->exists ? 'Edit Journal Post' : 'New Journal Post')
@section('content')
    @php
        $selectedCategories = collect(old('category_ids', $post->exists ? $post->categories->modelKeys() : []))->map(fn ($id) => (string) $id)->all();
        $selectedTags = collect(old('tag_ids', $post->exists ? $post->tags->modelKeys() : []))->map(fn ($id) => (string) $id)->all();
        $selectedVenues = collect(old('venue_ids', $post->exists ? $post->venues->modelKeys() : []))->map(fn ($id) => (string) $id)->all();
    @endphp

    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ $post->exists ? route('admin.journal-posts.update', $post) : route('admin.journal-posts.store') }}" class="admin-form">
        @csrf
        @if ($post->exists)
            @method('PUT')
        @endif

        <div class="field-grid">
            <label>
                Title
                <input type="text" name="title" value="{{ old('title', $post->title) }}" required>
            </label>

            <label>
                Slug
                <input type="text" name="slug" value="{{ old('slug', $post->slug) }}">
            </label>
        </div>

        <div class="field-grid">
            <label>
                Status
                <select name="status">
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(old('status', $post->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                Post type
                <select name="post_type">
                    @foreach ($postTypes as $postType)
                        <option value="{{ $postType }}" @selected(old('post_type', $post->post_type) === $postType)>{{ $postType }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <x-admin.media-picker
            name="hero_media_id"
            label="Hero media"
            help-text="Search by filename, alt text, or ID."
            :value="$post->hero_media_id"
            :media="$post->heroMedia"
        />

        <label>
            Author
            <input type="text" name="author_name" value="{{ old('author_name', $post->author_name) }}">
        </label>

        <label>
            Client gallery <span class="meta">(delivers this post's photos)</span>
            <select name="gallery_id">
                <option value="">None</option>
                @foreach ($galleries as $gallery)
                    <option value="{{ $gallery->id }}" @selected((string) old('gallery_id', $post->gallery_id) === (string) $gallery->id)>{{ $gallery->title }}</option>
                @endforeach
            </select>
        </label>

        <div class="field-grid">
            <label>
                Published at
                <input type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}">
            </label>

            <label>
                Legacy ID
                <input type="number" name="original_wp_post_id" value="{{ old('original_wp_post_id', $post->original_wp_post_id) }}">
            </label>
        </div>

        <label>
            Legacy URL
            <input type="url" name="original_wp_url" value="{{ old('original_wp_url', $post->original_wp_url) }}">
        </label>

        <label>
            Excerpt
            <textarea name="excerpt" rows="3">{{ old('excerpt', $post->excerpt) }}</textarea>
        </label>

        <label>
            Body
            <textarea name="body" rows="14">{{ old('body', $post->body) }}</textarea>
        </label>

        <div class="field-grid">
            <label>
                Categories
                <select name="category_ids[]" multiple size="8">
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(in_array((string) $category->id, $selectedCategories, true))>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                Tags
                <select name="tag_ids[]" multiple size="8">
                    @foreach ($tags as $tag)
                        <option value="{{ $tag->id }}" @selected(in_array((string) $tag->id, $selectedTags, true))>{{ $tag->name }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                Venues
                <select name="venue_ids[]" multiple size="8">
                    @foreach ($venues as $venue)
                        <option value="{{ $venue->id }}" @selected(in_array((string) $venue->id, $selectedVenues, true))>{{ $venue->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="field-grid">
            <label>
                SEO title
                <input type="text" name="seo_title" value="{{ old('seo_title', $post->seo_title) }}">
            </label>

            <label>
                Canonical URL
                <input type="url" name="canonical_url" value="{{ old('canonical_url', $post->canonical_url) }}">
            </label>
        </div>

        <label>
            SEO description
            <textarea name="seo_description" rows="3">{{ old('seo_description', $post->seo_description) }}</textarea>
        </label>

        <button class="cta" type="submit" style="border: 0; cursor: pointer;">Save Journal Post</button>
    </form>

    @if ($post->exists)
        <x-admin.story-gallery
            :owner="$post"
            :attach-url="route('admin.journal-posts.media.attach', $post)"
            :detach-url-pattern="route('admin.journal-posts.media.detach', ['journalPost' => $post, 'media' => '__id__'])"
            :reorder-url="route('admin.journal-posts.media.reorder', $post)"
            :hero-url-pattern="route('admin.journal-posts.media.hero', ['journalPost' => $post, 'media' => '__id__'])"
            :picker-url="route('admin.media.picker')"
        />

        @include('admin.blocks.manager', [
            'routePrefix' => 'admin.journal-posts.blocks',
            'ownerInRoute' => true,
            'owner' => $post,
            'blocks' => $post->allBlocks,
            'blockTypes' => $blockTypes,
            'managerTitle' => 'Journal Blocks',
        ])
    @endif
@endsection
