@props([])

@php
    /**
     * Shared block manager.
     *
     * Expects: $context ('page'|'homepage'), $owner (model), $blocks (allBlocks
     * collection), $blockTypes (registry slice for this context).
     */
    $blockRoute = function (string $name, array $params = []) use ($context, $owner) {
        return $context === 'page'
            ? route("admin.pages.$name", array_merge([$owner], $params))
            : route("admin.homepage.$name", $params);
    };
@endphp

<section class="admin-block-manager">
    <div class="admin-section-header">
        <h2>{{ $context === 'homepage' ? 'Homepage Sections' : 'Page Blocks' }}</h2>
        <p class="meta">Compose this {{ $context === 'homepage' ? 'homepage' : 'page' }} from stackable sections. They render top to bottom in sort order.</p>
    </div>

    @if ($context === 'homepage' && $blocks->isEmpty())
        <form method="POST" action="{{ $blockRoute('blocks.seed') }}" class="admin-form">
            @csrf
            <p class="section-copy">This homepage still uses the classic layout. Build the default sections to start editing them as blocks.</p>
            <button class="cta" type="submit" style="border: 0; cursor: pointer;">Build default homepage sections</button>
        </form>
    @endif

    @forelse ($blocks as $block)
        @php
            $usesMedia = data_get($blockTypes, $block->type.'.media', 0);
        @endphp
        <article class="admin-block-card">
            <header class="admin-block-card__header">
                <strong>{{ $block->typeLabel() }}</strong>
                <span class="meta">order {{ $block->sort_order }}@unless ($block->is_visible) · hidden @endunless</span>
            </header>

            <form method="POST" action="{{ $blockRoute('blocks.update', [$block]) }}" class="admin-form">
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
                                    <form method="POST" action="{{ $blockRoute('blocks.media.detach', [$block, $media]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="cta-secondary" type="submit">Remove</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <form method="POST" action="{{ $blockRoute('blocks.media.attach', [$block]) }}" class="admin-form">
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

            <form method="POST" action="{{ $blockRoute('blocks.destroy', [$block]) }}" onsubmit="return confirm('Delete this block?');">
                @csrf
                @method('DELETE')
                <button class="cta-secondary admin-block-card__delete" type="submit">Delete block</button>
            </form>
        </article>
    @empty
        @unless ($context === 'homepage')
            <p class="section-copy">No blocks yet. Add your first section below.</p>
        @endunless
    @endforelse

    @if ($blockTypes !== [])
        <form method="POST" action="{{ $blockRoute('blocks.store') }}" class="admin-form admin-block-add">
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
    @endif
</section>
