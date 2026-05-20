@extends('layouts.admin')

@section('title', $story->exists ? 'Edit Wedding Story' : 'New Wedding Story')
@section('heading', $story->exists ? 'Edit Wedding Story' : 'New Wedding Story')
@section('content')
    @php
        $selectedTags = collect(old('tag_ids', $story->exists ? $story->tags->modelKeys() : []))->map(fn ($id) => (string) $id)->all();
    @endphp

    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ $story->exists ? route('admin.wedding-stories.update', $story) : route('admin.wedding-stories.store') }}" class="admin-form">
        @csrf
        @if ($story->exists)
            @method('PUT')
        @endif

        <div class="field-grid">
            <label>
                Title
                <input type="text" name="title" value="{{ old('title', $story->title) }}" required>
            </label>

            <label>
                Slug
                <input type="text" name="slug" value="{{ old('slug', $story->slug) }}">
            </label>
        </div>

        <div class="field-grid">
            <label>
                Status
                <select name="status">
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(old('status', $story->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                Story type
                <select name="story_type">
                    @foreach ($storyTypes as $storyType)
                        <option value="{{ $storyType }}" @selected(old('story_type', $story->story_type) === $storyType)>{{ $storyType }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <x-admin.media-picker
            name="hero_media_id"
            label="Hero media"
            help-text="Search by filename, alt text, or ID."
            :value="$story->hero_media_id"
            :media="$story->heroMedia"
        />

        <label>
            Venue
            <select name="venue_id">
                <option value="">Select venue</option>
                @foreach ($venues as $venue)
                    <option value="{{ $venue->id }}" @selected((string) old('venue_id', $story->venue_id) === (string) $venue->id)>{{ $venue->name }}</option>
                @endforeach
            </select>
        </label>

        <div class="field-grid">
            <label>
                Event date
                <input type="date" name="event_date" value="{{ old('event_date', $story->event_date?->format('Y-m-d')) }}">
            </label>

            <label>
                Published at
                <input type="datetime-local" name="published_at" value="{{ old('published_at', $story->published_at?->format('Y-m-d\TH:i')) }}">
            </label>
        </div>

        <div class="field-grid">
            <label>
                Headline
                <input type="text" name="headline" value="{{ old('headline', $story->headline) }}">
            </label>

            <label>
                Client names
                <input type="text" name="client_names" value="{{ old('client_names', is_array($story->client_names) ? implode(', ', $story->client_names) : '') }}">
            </label>
        </div>

        <div class="field-grid">
            <label>
                Location name
                <input type="text" name="location_name" value="{{ old('location_name', $story->location_name) }}">
            </label>

            <label>
                City
                <input type="text" name="city" value="{{ old('city', $story->city) }}">
            </label>

            <label>
                State
                <input type="text" name="state" value="{{ old('state', $story->state) }}">
            </label>
        </div>

        <label>
            Excerpt
            <textarea name="excerpt" rows="3">{{ old('excerpt', $story->excerpt) }}</textarea>
        </label>

        <label>
            Body
            <textarea name="body" rows="14">{{ old('body', $story->body) }}</textarea>
        </label>

        <label>
            Tags
            <select name="tag_ids[]" multiple size="8">
                @foreach ($tags as $tag)
                    <option value="{{ $tag->id }}" @selected(in_array((string) $tag->id, $selectedTags, true))>{{ $tag->name }}</option>
                @endforeach
            </select>
        </label>

        <div class="field-grid">
            <label>
                SEO title
                <input type="text" name="seo_title" value="{{ old('seo_title', $story->seo_title) }}">
            </label>

            <label>
                Canonical URL
                <input type="url" name="canonical_url" value="{{ old('canonical_url', $story->canonical_url) }}">
            </label>
        </div>

        <label>
            SEO description
            <textarea name="seo_description" rows="3">{{ old('seo_description', $story->seo_description) }}</textarea>
        </label>

        <div class="field-grid">
            <label>
                Display order
                <input type="number" name="display_order" min="0" value="{{ old('display_order', $story->display_order ?? 0) }}">
            </label>

            <label class="admin-checkbox">
                <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $story->is_featured))>
                <span>Feature this story on the site</span>
            </label>
        </div>

        <button class="cta" type="submit" style="border: 0; cursor: pointer;">Save Story</button>
    </form>

    @if ($story->exists)
        <x-admin.story-gallery
            :owner="$story"
            :attach-url="route('admin.wedding-stories.media.attach', $story)"
            :detach-url-pattern="route('admin.wedding-stories.media.detach', ['weddingStory' => $story, 'media' => '__id__'])"
            :reorder-url="route('admin.wedding-stories.media.reorder', $story)"
            :hero-url-pattern="route('admin.wedding-stories.media.hero', ['weddingStory' => $story, 'media' => '__id__'])"
            :picker-url="route('admin.media.picker')"
        />
    @endif
@endsection
