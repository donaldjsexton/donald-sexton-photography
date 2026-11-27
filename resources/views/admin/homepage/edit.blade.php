@extends('layouts.admin-v2')

@section('title', 'Homepage Settings')
@section('eyebrow', 'Content')
@section('heading', 'Homepage Settings')
@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
    @endphp
    @php
        $selectedStories = collect(old('featured_story_ids_json', $settings->featured_story_ids_json ?? []))->map(fn ($id) => (string) $id)->all();
        $selectedTestimonials = collect(old('featured_testimonial_ids_json', $settings->featured_testimonial_ids_json ?? []))->map(fn ($id) => (string) $id)->all();
        $selectedPosts = collect(old('featured_journal_post_ids_json', $settings->featured_journal_post_ids_json ?? []))->map(fn ($id) => (string) $id)->all();
    @endphp

    @if ($errors->any())
        <ul class="mb-4 list-disc rounded-xl border border-rose-200 bg-rose-50 px-8 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('admin.homepage.update') }}" class="max-w-none! space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800/50">
        @csrf
        @method('PUT')

        <x-ui.field label="Hero heading">
            <input class="{{ $input }}" type="text" name="hero_heading" value="{{ old('hero_heading', $settings->hero_heading) }}">
        </x-ui.field>

        <x-ui.media-picker
            name="hero_media_id"
            label="Hero media"
            help-text="Search by filename, alt text, or ID."
            :value="$settings->hero_media_id"
            :media="$settings->heroMedia"
        />

        <x-ui.field label="Hero subheading">
            <textarea class="{{ $input }}" name="hero_subheading" rows="4">{{ old('hero_subheading', $settings->hero_subheading) }}</textarea>
        </x-ui.field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Featured wedding stories">
                <select class="{{ $input }}" name="featured_story_ids_json[]" multiple size="8">
                    @foreach ($stories as $story)
                        <option value="{{ $story->id }}" @selected(in_array((string) $story->id, $selectedStories, true))>{{ $story->title }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Featured journal posts">
                <select class="{{ $input }}" name="featured_journal_post_ids_json[]" multiple size="8">
                    @foreach ($journalPosts as $post)
                        <option value="{{ $post->id }}" @selected(in_array((string) $post->id, $selectedPosts, true))>{{ $post->title }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        <x-ui.field label="Featured testimonials">
            <select class="{{ $input }}" name="featured_testimonial_ids_json[]" multiple size="8">
                @foreach ($testimonials as $testimonial)
                    <option value="{{ $testimonial->id }}" @selected(in_array((string) $testimonial->id, $selectedTestimonials, true))>{{ $testimonial->author_name }} · {{ \Illuminate\Support\Str::limit($testimonial->quote, 55) }}</option>
                @endforeach
            </select>
        </x-ui.field>

        <x-ui.field label="Investment teaser">
            <textarea class="{{ $input }}" name="investment_teaser" rows="3">{{ old('investment_teaser', $settings->investment_teaser) }}</textarea>
        </x-ui.field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Final CTA heading">
                <input class="{{ $input }}" type="text" name="final_cta_heading" value="{{ old('final_cta_heading', $settings->final_cta_heading) }}">
            </x-ui.field>

            <x-ui.field label="Final CTA body">
                <textarea class="{{ $input }}" name="final_cta_body" rows="3">{{ old('final_cta_body', $settings->final_cta_body) }}</textarea>
            </x-ui.field>
        </div>

        <x-ui.button type="submit">Save Homepage Settings</x-ui.button>
    </form>

    @include('admin.blocks.manager-v2', [
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
