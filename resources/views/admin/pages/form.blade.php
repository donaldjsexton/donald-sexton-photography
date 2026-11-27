@extends('layouts.admin-v2')

@section('title', $page->exists ? 'Edit Page' : 'New Page')
@section('eyebrow', 'Content')
@section('heading', $page->exists ? 'Edit Page' : 'New Page')
@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
    @endphp
    @if ($errors->any())
        <ul class="mb-4 list-disc rounded-xl border border-rose-200 bg-rose-50 px-8 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="max-w-none! space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800/50">
        @csrf
        @if ($page->exists)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Title">
                <input class="{{ $input }}" type="text" name="title" value="{{ old('title', $page->title) }}" required>
            </x-ui.field>

            <x-ui.field label="Slug">
                <input class="{{ $input }}" type="text" name="slug" value="{{ old('slug', $page->slug) }}">
            </x-ui.field>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Template">
                <select class="{{ $input }}" name="template">
                    @foreach ($templates as $template)
                        <option value="{{ $template }}" @selected(old('template', $page->template) === $template)>{{ $template }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Status">
                <select class="{{ $input }}" name="status">
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(old('status', $page->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </x-ui.field>
        </div>

        <x-ui.media-picker
            name="hero_media_id"
            label="Hero media"
            help-text="Search by filename, alt text, or ID."
            :value="$page->hero_media_id"
            :media="$page->heroMedia"
        />

        <x-ui.field label="Publish at">
            <input class="{{ $input }}" type="datetime-local" name="published_at" value="{{ old('published_at', $page->published_at?->format('Y-m-d\TH:i')) }}">
        </x-ui.field>

        <x-ui.field label="Excerpt">
            <textarea class="{{ $input }}" name="excerpt" rows="3">{{ old('excerpt', $page->excerpt) }}</textarea>
        </x-ui.field>

        <x-ui.field label="Body">
            <textarea class="{{ $input }}" name="body" rows="14">{{ old('body', $page->body) }}</textarea>
        </x-ui.field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="SEO title">
                <input class="{{ $input }}" type="text" name="seo_title" value="{{ old('seo_title', $page->seo_title) }}">
            </x-ui.field>

            <x-ui.field label="Canonical URL">
                <input class="{{ $input }}" type="url" name="canonical_url" value="{{ old('canonical_url', $page->canonical_url) }}">
            </x-ui.field>
        </div>

        <x-ui.field label="SEO description">
            <textarea class="{{ $input }}" name="seo_description" rows="3">{{ old('seo_description', $page->seo_description) }}</textarea>
        </x-ui.field>

        @php
            $faqsText = old('faqs_text', collect($page->structuredFaqs())
                ->map(fn (array $item) => $item['question'].' | '.$item['answer'])
                ->implode("\n"));
        @endphp

        <x-ui.field label="FAQs">
            <textarea class="{{ $input }}" name="faqs_text" rows="6" placeholder="Do you photograph weddings in this area? | Yes — this area is part of my regular coverage, with no travel fee inside Tampa Bay.
How far in advance should we book? | Most couples book nine to fourteen months ahead of their date.">{{ $faqsText }}</textarea>
            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">One FAQ per line, formatted <code>Question | Answer</code>. These render on the page and emit FAQPage schema so Google can surface them in search.</span>
        </x-ui.field>

        <x-ui.field label="Sort order">
            <input class="{{ $input }}" type="number" name="sort_order" min="0" value="{{ old('sort_order', $page->sort_order ?? 0) }}">
        </x-ui.field>

        <x-ui.button type="submit">Save Page</x-ui.button>
    </form>

    @if ($page->exists)
        @include('admin.blocks.manager-v2', [
            'routePrefix' => 'admin.pages.blocks',
            'ownerInRoute' => true,
            'owner' => $page,
            'blocks' => $page->allBlocks,
            'blockTypes' => $blockTypes,
            'managerTitle' => 'Page Blocks',
        ])
    @endif
@endsection
