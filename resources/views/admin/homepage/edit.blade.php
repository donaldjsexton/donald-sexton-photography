@extends('layouts.admin')

@section('title', 'Homepage Settings')
@section('heading', 'Homepage Settings')
@section('content')
    @php
        $selectedStories = collect(old('featured_story_ids_json', $settings->featured_story_ids_json ?? []))->map(fn ($id) => (string) $id)->all();
        $selectedTestimonials = collect(old('featured_testimonial_ids_json', $settings->featured_testimonial_ids_json ?? []))->map(fn ($id) => (string) $id)->all();
        $selectedPosts = collect(old('featured_journal_post_ids_json', $settings->featured_journal_post_ids_json ?? []))->map(fn ($id) => (string) $id)->all();
    @endphp

    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('admin.homepage.update') }}" class="admin-form">
        @csrf
        @method('PUT')

        <label>
            Hero heading
            <input type="text" name="hero_heading" value="{{ old('hero_heading', $settings->hero_heading) }}">
        </label>

        <x-admin.media-picker
            name="hero_media_id"
            label="Hero media"
            help-text="Search by filename, alt text, or ID."
            :value="$settings->hero_media_id"
            :media="$settings->heroMedia"
        />

        <label>
            Hero subheading
            <textarea name="hero_subheading" rows="4">{{ old('hero_subheading', $settings->hero_subheading) }}</textarea>
        </label>

        <div class="field-grid">
            <label>
                Featured wedding stories
                <select name="featured_story_ids_json[]" multiple size="8">
                    @foreach ($stories as $story)
                        <option value="{{ $story->id }}" @selected(in_array((string) $story->id, $selectedStories, true))>{{ $story->title }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                Featured journal posts
                <select name="featured_journal_post_ids_json[]" multiple size="8">
                    @foreach ($journalPosts as $post)
                        <option value="{{ $post->id }}" @selected(in_array((string) $post->id, $selectedPosts, true))>{{ $post->title }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <label>
            Featured testimonials
            <select name="featured_testimonial_ids_json[]" multiple size="8">
                @foreach ($testimonials as $testimonial)
                    <option value="{{ $testimonial->id }}" @selected(in_array((string) $testimonial->id, $selectedTestimonials, true))>{{ $testimonial->author_name }} · {{ \Illuminate\Support\Str::limit($testimonial->quote, 55) }}</option>
                @endforeach
            </select>
        </label>

        <label>
            Investment teaser
            <textarea name="investment_teaser" rows="3">{{ old('investment_teaser', $settings->investment_teaser) }}</textarea>
        </label>

        <div class="field-grid">
            <label>
                Final CTA heading
                <input type="text" name="final_cta_heading" value="{{ old('final_cta_heading', $settings->final_cta_heading) }}">
            </label>

            <label>
                Final CTA body
                <textarea name="final_cta_body" rows="3">{{ old('final_cta_body', $settings->final_cta_body) }}</textarea>
            </label>
        </div>

        <button class="cta" type="submit" style="border: 0; cursor: pointer;">Save Homepage Settings</button>
    </form>

    @include('admin.blocks.manager', [
        'routePrefix' => 'admin.homepage.blocks',
        'ownerInRoute' => false,
        'owner' => $settings,
        'blocks' => $homeBlocks,
        'blockTypes' => $blockTypes,
        'managerTitle' => 'Homepage Sections',
        'seedRoute' => 'admin.homepage.blocks.seed',
        'emptyHint' => false,
    ])
@endsection
