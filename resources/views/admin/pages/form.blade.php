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
        <section class="admin-block-manager">
            <div class="admin-section-header">
                <h2>Page Blocks</h2>
                <p class="meta">Compose this page from stackable sections. Blocks render top to bottom in sort order.</p>
            </div>

            @forelse ($page->allBlocks as $block)
                @php
                    $usesMedia = data_get($blockTypes, $block->type.'.media', 0);
                @endphp
                <article class="admin-block-card">
                    <header class="admin-block-card__header">
                        <strong>{{ $block->typeLabel() }}</strong>
                        <span class="meta">order {{ $block->sort_order }}@unless ($block->is_visible) · hidden @endunless</span>
                    </header>

                    <form method="POST" action="{{ route('admin.pages.blocks.update', [$page, $block]) }}" class="admin-form">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="type" value="{{ $block->type }}">

                        <div class="field-grid">
                            <label>
                                Heading
                                <input type="text" name="heading" value="{{ old('heading', $block->heading) }}">
                            </label>
                            <label>
                                Subheading
                                <input type="text" name="subheading" value="{{ old('subheading', $block->subheading) }}">
                            </label>
                        </div>

                        <label>
                            Body
                            <textarea name="body" rows="5">{{ old('body', $block->body) }}</textarea>
                        </label>

                        @if ($block->type === 'cta')
                            <div class="field-grid">
                                <label>
                                    Primary button URL
                                    <input type="text" name="data[primary_url]" value="{{ old('data.primary_url', data_get($block->data, 'primary_url')) }}">
                                </label>
                                <label>
                                    Primary button label
                                    <input type="text" name="data[primary_label]" value="{{ old('data.primary_label', data_get($block->data, 'primary_label')) }}">
                                </label>
                                <label>
                                    Secondary button URL
                                    <input type="text" name="data[secondary_url]" value="{{ old('data.secondary_url', data_get($block->data, 'secondary_url')) }}">
                                </label>
                                <label>
                                    Secondary button label
                                    <input type="text" name="data[secondary_label]" value="{{ old('data.secondary_label', data_get($block->data, 'secondary_label')) }}">
                                </label>
                            </div>
                        @endif

                        <div class="field-grid">
                            <label>
                                Sort order
                                <input type="number" name="sort_order" min="0" value="{{ old('sort_order', $block->sort_order) }}">
                            </label>
                            <label class="admin-checkbox">
                                <input type="hidden" name="is_visible" value="0">
                                <input type="checkbox" name="is_visible" value="1" @checked($block->is_visible)>
                                Visible
                            </label>
                        </div>

                        <button class="cta-secondary" type="submit">Save block</button>
                    </form>

                    @if ($usesMedia !== 0)
                        <div class="admin-block-card__media">
                            <p class="meta">Images</p>
                            @if ($block->media->isNotEmpty())
                                <ul class="admin-block-media-list">
                                    @foreach ($block->media as $media)
                                        <li>
                                            @if ($media->publicUrl())
                                                <img src="{{ $media->publicUrl() }}" alt="{{ $media->alt_text ?: $media->filename }}" loading="lazy">
                                            @endif
                                            <span class="meta">{{ $media->filename }}</span>
                                            <form method="POST" action="{{ route('admin.pages.blocks.media.detach', [$page, $block, $media]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="cta-secondary" type="submit">Remove</button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <form method="POST" action="{{ route('admin.pages.blocks.media.attach', [$page, $block]) }}" class="admin-form">
                                @csrf
                                <x-admin.media-picker
                                    name="media_id"
                                    label="Attach image"
                                    help-text="Search by filename, alt text, or ID."
                                />
                                <button class="cta-secondary" type="submit">Attach image</button>
                            </form>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.pages.blocks.destroy', [$page, $block]) }}" onsubmit="return confirm('Delete this block?');">
                        @csrf
                        @method('DELETE')
                        <button class="cta-secondary admin-block-card__delete" type="submit">Delete block</button>
                    </form>
                </article>
            @empty
                <p class="section-copy">No blocks yet. Add your first section below.</p>
            @endforelse

            <form method="POST" action="{{ route('admin.pages.blocks.store', $page) }}" class="admin-form admin-block-add">
                @csrf
                <div class="field-grid">
                    <label>
                        Block type
                        <select name="type">
                            @foreach ($blockTypes as $key => $definition)
                                <option value="{{ $key }}">{{ $definition['label'] ?? $key }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Heading
                        <input type="text" name="heading">
                    </label>
                </div>
                <button class="cta" type="submit" style="border: 0; cursor: pointer;">Add block</button>
            </form>
        </section>
    @endif
@endsection
