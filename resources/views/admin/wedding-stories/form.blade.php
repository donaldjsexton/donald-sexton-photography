@extends('layouts.admin-v2')

@section('title', $story->exists ? 'Edit Wedding Story' : 'New Wedding Story')
@section('eyebrow', 'Content')
@section('heading', $story->exists ? 'Edit Wedding Story' : 'New Wedding Story')
@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
    @endphp
    @php
        $selectedTags = collect(old('tag_ids', $story->exists ? $story->tags->modelKeys() : []))->map(fn ($id) => (string) $id)->all();
    @endphp

    @if ($errors->any())
        <ul class="mb-4 list-disc rounded-xl border border-rose-200 bg-rose-50 px-8 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ $story->exists ? route('admin.wedding-stories.update', $story) : route('admin.wedding-stories.store') }}" class="max-w-none! space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800/50">
        @csrf
        @if ($story->exists)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Title">
                <input class="{{ $input }}" type="text" name="title" value="{{ old('title', $story->title) }}" required>
            </x-ui.field>

            <x-ui.field label="Slug">
                <input class="{{ $input }}" type="text" name="slug" value="{{ old('slug', $story->slug) }}">
            </x-ui.field>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Status">
                <select class="{{ $input }}" name="status">
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(old('status', $story->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Story type">
                <select class="{{ $input }}" name="story_type">
                    @foreach ($storyTypes as $storyType)
                        <option value="{{ $storyType }}" @selected(old('story_type', $story->story_type) === $storyType)>{{ $storyType }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        <x-ui.media-picker
            name="hero_media_id"
            label="Hero media"
            help-text="Search by filename, alt text, or ID."
            :value="$story->hero_media_id"
            :media="$story->heroMedia"
        />

        <x-ui.field label="Venue">
            <select class="{{ $input }}" name="venue_id">
                <option value="">Select venue</option>
                @foreach ($venues as $venue)
                    <option value="{{ $venue->id }}" @selected((string) old('venue_id', $story->venue_id) === (string) $venue->id)>{{ $venue->name }}</option>
                @endforeach
            </select>
        </x-ui.field>

        <x-ui.field label="Client gallery (delivers this story's photos)">
            <select class="{{ $input }}" name="gallery_id">
                <option value="">None</option>
                @foreach ($galleries as $gallery)
                    <option value="{{ $gallery->id }}" @selected((string) old('gallery_id', $story->gallery_id) === (string) $gallery->id)>{{ $gallery->title }}</option>
                @endforeach
            </select>
        </x-ui.field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Event date">
                <input class="{{ $input }}" type="date" name="event_date" value="{{ old('event_date', $story->event_date?->format('Y-m-d')) }}">
            </x-ui.field>

            <x-ui.field label="Published at">
                <input class="{{ $input }}" type="datetime-local" name="published_at" value="{{ old('published_at', $story->published_at?->format('Y-m-d\TH:i')) }}">
            </x-ui.field>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Headline">
                <input class="{{ $input }}" type="text" name="headline" value="{{ old('headline', $story->headline) }}">
            </x-ui.field>

            <x-ui.field label="Client names">
                <input class="{{ $input }}" type="text" name="client_names" value="{{ old('client_names', is_array($story->client_names) ? implode(', ', $story->client_names) : '') }}">
            </x-ui.field>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.field label="Location name">
                <input class="{{ $input }}" type="text" name="location_name" value="{{ old('location_name', $story->location_name) }}">
            </x-ui.field>

            <x-ui.field label="City">
                <input class="{{ $input }}" type="text" name="city" value="{{ old('city', $story->city) }}">
            </x-ui.field>

            <x-ui.field label="State">
                <input class="{{ $input }}" type="text" name="state" value="{{ old('state', $story->state) }}">
            </x-ui.field>
        </div>

        <x-ui.field label="Excerpt">
            <textarea class="{{ $input }}" name="excerpt" rows="3">{{ old('excerpt', $story->excerpt) }}</textarea>
        </x-ui.field>

        <x-ui.field label="Body">
            <textarea class="{{ $input }}" name="body" rows="14">{{ old('body', $story->body) }}</textarea>
            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">Saved as HTML. Separate paragraphs with <code>&lt;p&gt;</code> tags. A link is <code>&lt;a href="https://…"&gt;label&lt;/a&gt;</code>. Markdown is not rendered.</span>
        </x-ui.field>

        <x-ui.field label="Tags">
            <select class="{{ $input }}" name="tag_ids[]" multiple size="8">
                @foreach ($tags as $tag)
                    <option value="{{ $tag->id }}" @selected(in_array((string) $tag->id, $selectedTags, true))>{{ $tag->name }}</option>
                @endforeach
            </select>
        </x-ui.field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="SEO title">
                <input class="{{ $input }}" type="text" name="seo_title" value="{{ old('seo_title', $story->seo_title) }}">
            </x-ui.field>

            <x-ui.field label="Canonical URL">
                <input class="{{ $input }}" type="url" name="canonical_url" value="{{ old('canonical_url', $story->canonical_url) }}">
            </x-ui.field>
        </div>

        <x-ui.field label="SEO description">
            <textarea class="{{ $input }}" name="seo_description" rows="3">{{ old('seo_description', $story->seo_description) }}</textarea>
        </x-ui.field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Display order">
                <input class="{{ $input }}" type="number" name="display_order" min="0" value="{{ old('display_order', $story->display_order ?? 0) }}">
            </x-ui.field>

            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $story->is_featured))>
                <span>Feature this story on the site</span>
            </label>
        </div>

        <x-ui.button type="submit">Save Story</x-ui.button>
    </form>

    @if ($story->exists)
        <x-ui.story-gallery
            :owner="$story"
            :attach-url="route('admin.wedding-stories.media.attach', $story)"
            :detach-url-pattern="route('admin.wedding-stories.media.detach', ['weddingStory' => $story, 'media' => '__id__'])"
            :reorder-url="route('admin.wedding-stories.media.reorder', $story)"
            :hero-url-pattern="route('admin.wedding-stories.media.hero', ['weddingStory' => $story, 'media' => '__id__'])"
            :picker-url="route('admin.media.picker')"
        />
    @endif
@endsection
