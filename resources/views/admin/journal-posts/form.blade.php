@extends('layouts.admin-v2')

@section('title', $post->exists ? 'Edit Journal Post' : 'New Journal Post')
@section('eyebrow', 'Content')
@section('heading', $post->exists ? 'Edit Journal Post' : 'New Journal Post')
@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
    @endphp
    @php
        $selectedCategories = collect(old('category_ids', $post->exists ? $post->categories->modelKeys() : []))->map(fn ($id) => (string) $id)->all();
        $selectedTags = collect(old('tag_ids', $post->exists ? $post->tags->modelKeys() : []))->map(fn ($id) => (string) $id)->all();
        $selectedVenues = collect(old('venue_ids', $post->exists ? $post->venues->modelKeys() : []))->map(fn ($id) => (string) $id)->all();
    @endphp

    @if ($errors->any())
        <ul class="mb-4 list-disc rounded-xl border border-rose-200 bg-rose-50 px-8 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ $post->exists ? route('admin.journal-posts.update', $post) : route('admin.journal-posts.store') }}" class="max-w-none! space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800/50">
        @csrf
        @if ($post->exists)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Title">
                <input class="{{ $input }}" type="text" name="title" value="{{ old('title', $post->title) }}" required>
            </x-ui.field>

            <x-ui.field label="Slug">
                <input class="{{ $input }}" type="text" name="slug" value="{{ old('slug', $post->slug) }}">
            </x-ui.field>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Status">
                <select class="{{ $input }}" name="status">
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(old('status', $post->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Post type">
                <select class="{{ $input }}" name="post_type">
                    @foreach ($postTypes as $postType)
                        <option value="{{ $postType }}" @selected(old('post_type', $post->post_type) === $postType)>{{ $postType }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        <x-ui.media-picker
            name="hero_media_id"
            label="Hero media"
            help-text="Search by filename, alt text, or ID."
            :value="$post->hero_media_id"
            :media="$post->heroMedia"
        />

        <x-ui.field label="Author">
            <input class="{{ $input }}" type="text" name="author_name" value="{{ old('author_name', $post->author_name) }}">
        </x-ui.field>

        <x-ui.field label="Client gallery (delivers this post's photos)">
            <select class="{{ $input }}" name="gallery_id">
                <option value="">None</option>
                @foreach ($galleries as $gallery)
                    <option value="{{ $gallery->id }}" @selected((string) old('gallery_id', $post->gallery_id) === (string) $gallery->id)>{{ $gallery->title }}</option>
                @endforeach
            </select>
        </x-ui.field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Published at">
                <input class="{{ $input }}" type="datetime-local" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}">
            </x-ui.field>

            <x-ui.field label="Legacy ID">
                <input class="{{ $input }}" type="number" name="original_wp_post_id" value="{{ old('original_wp_post_id', $post->original_wp_post_id) }}">
            </x-ui.field>
        </div>

        <x-ui.field label="Legacy URL">
            <input class="{{ $input }}" type="url" name="original_wp_url" value="{{ old('original_wp_url', $post->original_wp_url) }}">
        </x-ui.field>

        <x-ui.field label="Excerpt">
            <textarea class="{{ $input }}" name="excerpt" rows="3">{{ old('excerpt', $post->excerpt) }}</textarea>
        </x-ui.field>

        <x-ui.field label="Body">
            <textarea class="{{ $input }}" name="body" rows="14">{{ old('body', $post->body) }}</textarea>
        </x-ui.field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.field label="Categories">
                <select class="{{ $input }}" name="category_ids[]" multiple size="8">
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(in_array((string) $category->id, $selectedCategories, true))>{{ $category->name }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Tags">
                <select class="{{ $input }}" name="tag_ids[]" multiple size="8">
                    @foreach ($tags as $tag)
                        <option value="{{ $tag->id }}" @selected(in_array((string) $tag->id, $selectedTags, true))>{{ $tag->name }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Venues">
                <select class="{{ $input }}" name="venue_ids[]" multiple size="8">
                    @foreach ($venues as $venue)
                        <option value="{{ $venue->id }}" @selected(in_array((string) $venue->id, $selectedVenues, true))>{{ $venue->name }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="SEO title">
                <input class="{{ $input }}" type="text" name="seo_title" value="{{ old('seo_title', $post->seo_title) }}">
            </x-ui.field>

            <x-ui.field label="Canonical URL">
                <input class="{{ $input }}" type="url" name="canonical_url" value="{{ old('canonical_url', $post->canonical_url) }}">
            </x-ui.field>
        </div>

        <x-ui.field label="SEO description">
            <textarea class="{{ $input }}" name="seo_description" rows="3">{{ old('seo_description', $post->seo_description) }}</textarea>
        </x-ui.field>

        <x-ui.button type="submit">Save Journal Post</x-ui.button>
    </form>

    @if ($post->exists)
        <x-ui.story-gallery
            :owner="$post"
            :attach-url="route('admin.journal-posts.media.attach', $post)"
            :detach-url-pattern="route('admin.journal-posts.media.detach', ['journalPost' => $post, 'media' => '__id__'])"
            :reorder-url="route('admin.journal-posts.media.reorder', $post)"
            :hero-url-pattern="route('admin.journal-posts.media.hero', ['journalPost' => $post, 'media' => '__id__'])"
            :picker-url="route('admin.media.picker')"
        />

        @include('admin.blocks.manager-v2', [
            'routePrefix' => 'admin.journal-posts.blocks',
            'ownerInRoute' => true,
            'owner' => $post,
            'blocks' => $post->allBlocks,
            'blockTypes' => $blockTypes,
            'managerTitle' => 'Journal Blocks',
        ])
    @endif
@endsection
