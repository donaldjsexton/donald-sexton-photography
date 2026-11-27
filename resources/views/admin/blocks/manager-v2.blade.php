@props([])

@php
    /**
     * Block manager for the admin-v2 layout (same inputs as manager.blade.php):
     *  $routePrefix, $ownerInRoute, $owner, $blocks, $blockTypes, $managerTitle, $seedRoute, $emptyHint.
     * Field names, data-block-* hooks and routes are unchanged; drag-reorder is
     * handled by resources/js/admin-widgets.js.
     */
    $managerTitle ??= 'Blocks';
    $seedRoute ??= null;
    $emptyHint ??= true;
    $ownerInRoute ??= true;
    $owner ??= null;

    $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
    $hint = 'text-sm text-gray-500 dark:text-gray-400';

    $blockRoute = function (string $action, array $params = []) use ($routePrefix, $ownerInRoute, $owner) {
        $all = $ownerInRoute ? array_merge([$owner], $params) : $params;

        return route("{$routePrefix}.{$action}", $all);
    };
@endphp

<section class="mt-10 space-y-5" data-block-manager data-reorder-url="{{ $blockRoute('reorder') }}">
    <div>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $managerTitle }}</h2>
        <p class="{{ $hint }}">Compose this surface from stackable sections. Drag the ⋮⋮ handle to reorder, or set sort order by hand.</p>
    </div>

    @if ($seedRoute && $blocks->isEmpty())
        <form method="POST" action="{{ route($seedRoute) }}" class="max-w-none! space-y-3">
            @csrf
            <p class="{{ $hint }}">This surface still uses the classic layout. Build the default sections to start editing them as blocks.</p>
            <x-ui.button type="submit">Build default sections</x-ui.button>
        </form>
    @endif

    <div class="space-y-4" data-block-list>
    @forelse ($blocks as $block)
        @php
            $usesMedia = data_get($blockTypes, $block->type.'.media', 0);
        @endphp
        <article class="admin-block-card space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800/50" data-block-card data-block-id="{{ $block->id }}" draggable="true">
            <header class="flex items-center gap-3">
                <span class="admin-block-card__handle grid size-8 place-items-center rounded-md bg-gray-100 text-sm leading-none dark:bg-gray-700" data-block-handle aria-label="Drag to reorder" title="Drag to reorder">⋮⋮</span>
                <strong class="text-gray-900 dark:text-white">{{ $block->typeLabel() }}</strong>
                <span class="ml-auto text-xs text-gray-500 dark:text-gray-400">order {{ $block->sort_order }}@unless ($block->is_visible) · hidden @endunless</span>
            </header>

            <form method="POST" action="{{ $blockRoute('update', [$block]) }}" class="max-w-none! space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="type" value="{{ $block->type }}">

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-ui.field label="Heading">
                        <input class="{{ $input }}" type="text" name="heading" value="{{ old('heading', $block->heading) }}">
                    </x-ui.field>
                    <x-ui.field label="Subheading">
                        <input class="{{ $input }}" type="text" name="subheading" value="{{ old('subheading', $block->subheading) }}">
                    </x-ui.field>
                </div>

                <x-ui.field label="Body">
                    <textarea class="{{ $input }}" name="body" rows="5">{{ old('body', $block->body) }}</textarea>
                </x-ui.field>

                @if ($block->type === 'cta')
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-ui.field label="Primary button URL">
                            <input class="{{ $input }}" type="text" name="data[primary_url]" value="{{ old('data.primary_url', data_get($block->data, 'primary_url')) }}">
                        </x-ui.field>
                        <x-ui.field label="Primary button label">
                            <input class="{{ $input }}" type="text" name="data[primary_label]" value="{{ old('data.primary_label', data_get($block->data, 'primary_label')) }}">
                        </x-ui.field>
                        <x-ui.field label="Secondary button URL">
                            <input class="{{ $input }}" type="text" name="data[secondary_url]" value="{{ old('data.secondary_url', data_get($block->data, 'secondary_url')) }}">
                        </x-ui.field>
                        <x-ui.field label="Secondary button label">
                            <input class="{{ $input }}" type="text" name="data[secondary_label]" value="{{ old('data.secondary_label', data_get($block->data, 'secondary_label')) }}">
                        </x-ui.field>
                    </div>
                @endif

                <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2">
                    <x-ui.field label="Sort order">
                        <input class="{{ $input }}" type="number" name="sort_order" min="0" value="{{ old('sort_order', $block->sort_order) }}">
                    </x-ui.field>
                    <label class="inline-flex items-center gap-2 pb-2.5 text-sm text-gray-700 dark:text-gray-300">
                        <input type="hidden" name="is_visible" value="0">
                        <input type="checkbox" name="is_visible" value="1" @checked($block->is_visible)>
                        Visible
                    </label>
                </div>

                <x-ui.button variant="secondary" type="submit">Save block</x-ui.button>
            </form>

            @if ($usesMedia !== 0)
                <div class="space-y-3 border-t border-dashed border-gray-300 pt-4 dark:border-gray-600">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Images</p>
                    @if ($block->media->isNotEmpty())
                        <ul class="space-y-2">
                            @foreach ($block->media as $media)
                                <li class="flex flex-wrap items-center gap-3">
                                    @if ($media->publicUrl())
                                        <img class="size-14 rounded-md object-cover" src="{{ $media->publicUrl() }}" alt="{{ $media->alt_text ?: $media->filename }}" loading="lazy">
                                    @endif
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $media->filename }}</span>
                                    <form method="POST" action="{{ $blockRoute('media.detach', [$block, $media]) }}" class="max-w-none!">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button variant="secondary" type="submit">Remove</x-ui.button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <form method="POST" action="{{ $blockRoute('media.attach', [$block]) }}" class="max-w-none! space-y-3">
                        @csrf
                        <x-ui.media-picker
                            name="media_id"
                            label="Attach image"
                            help-text="Search by filename, alt text, or ID."
                        />
                        <x-ui.button variant="secondary" type="submit">Attach image</x-ui.button>
                    </form>
                </div>
            @endif

            <form method="POST" action="{{ $blockRoute('destroy', [$block]) }}" class="max-w-none!" onsubmit="return confirm('Delete this block?');">
                @csrf
                @method('DELETE')
                <x-ui.button variant="danger" type="submit">Delete block</x-ui.button>
            </form>
        </article>
    @empty
        @if ($emptyHint)
            <p class="{{ $hint }}">No blocks yet. Add your first section below.</p>
        @endif
    @endforelse
    </div>

    @if ($blockTypes !== [])
        <form method="POST" action="{{ $blockRoute('store') }}" class="max-w-none! space-y-4 rounded-2xl border border-dashed border-gray-300 p-5 dark:border-gray-600">
            @csrf
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Block type">
                    <select class="{{ $input }}" name="type">
                        @foreach ($blockTypes as $key => $definition)
                            <option value="{{ $key }}">{{ $definition['label'] ?? $key }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Heading">
                    <input class="{{ $input }}" type="text" name="heading">
                </x-ui.field>
            </div>
            <x-ui.button type="submit">Add block</x-ui.button>
        </form>
    @endif
</section>
