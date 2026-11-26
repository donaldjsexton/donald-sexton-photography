@extends('layouts.admin-v2')

@section('title', 'Media')
@section('eyebrow', 'Photo library')
@section('heading', 'Media Library')
@section('subheading', 'Every image used across hero areas, stories, journal posts, pages, and venues.')
@section('header_actions')
    <x-ui.button :href="route('admin.media.create')">＋ Upload photo</x-ui.button>
@endsection
@section('content')
    @php
        $filterLabels = [
            'all' => 'All',
            'recent' => 'Last 30 days',
            'unused' => 'Orphaned',
            'missing-alt' => 'Missing alt',
            'pictime' => 'Pic-Time',
            'wp' => 'Legacy WP',
        ];
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
        $statBase = 'media-stat block rounded-2xl border bg-white p-5 shadow-sm transition-colors hover:bg-gray-50 dark:bg-gray-800/50 dark:hover:bg-gray-800';
        $statIdle = 'border-gray-200 dark:border-gray-800';
        $statOn = 'is-active border-brand-500 ring-2 ring-brand-500/20';
        $statValue = 'media-stat__value block text-2xl font-semibold text-gray-900 dark:text-white';
        $statLabel = 'media-stat__label mt-1 block text-sm text-gray-500 dark:text-gray-400';
        $pill = 'media-filter rounded-full px-3.5 py-1.5 text-sm font-medium transition-colors';
        $on = 'is-active bg-brand-500 text-white';
        $off = 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700 dark:hover:bg-gray-700';
        $chip = 'inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700/60 dark:text-gray-300';
        $link = 'text-brand-600 hover:underline dark:text-brand-400';
    @endphp

    <div class="media-stats [display:grid] grid-cols-2 gap-4 lg:grid-cols-5">
        <a class="{{ $statBase }} {{ $filter === 'all' ? $statOn : $statIdle }}" href="{{ route('admin.media.index') }}">
            <span class="{{ $statValue }}">{{ number_format($stats['total']) }}</span>
            <span class="{{ $statLabel }}">Total</span>
        </a>
        <a class="{{ $statBase }} {{ $statIdle }}" href="{{ route('admin.media.index') }}#used">
            <span class="{{ $statValue }}">{{ number_format($stats['used']) }}</span>
            <span class="{{ $statLabel }}">In use</span>
        </a>
        <a class="{{ $statBase }} {{ $filter === 'unused' ? $statOn : $statIdle }}" href="{{ route('admin.media.index', ['filter' => 'unused']) }}">
            <span class="{{ $statValue }}">{{ number_format($stats['orphaned']) }}</span>
            <span class="{{ $statLabel }}">Orphaned</span>
        </a>
        <a class="{{ $statBase }} {{ $stats['missing_alt'] > 0 ? 'media-stat--alert' : 'media-stat--ok' }} {{ $filter === 'missing-alt' ? $statOn : $statIdle }}"
           href="{{ route('admin.media.index', ['filter' => 'missing-alt']) }}">
            <span class="{{ $statValue }} {{ $stats['missing_alt'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $stats['missing_alt'] > 0 ? number_format($stats['missing_alt']) : '✓' }}</span>
            <span class="{{ $statLabel }}">Missing alt</span>
        </a>
        <a class="{{ $statBase }} {{ $filter === 'recent' ? $statOn : $statIdle }}" href="{{ route('admin.media.index', ['filter' => 'recent']) }}">
            <span class="{{ $statValue }}">{{ number_format($stats['this_month']) }}</span>
            <span class="{{ $statLabel }}">This month</span>
        </a>
    </div>

    @if ($recent->isNotEmpty() && $filter === 'all' && $search === '')
        <x-ui.card class="media-feed mt-6" aria-label="Recently added photos">
            <header class="media-feed__head mb-3 flex items-baseline justify-between gap-3">
                <h3 class="media-feed__title text-base font-semibold text-gray-900 dark:text-white">Fresh uploads</h3>
                <span class="media-feed__meta text-sm text-gray-500 dark:text-gray-400">Latest {{ $recent->count() }}</span>
            </header>
            <ol class="media-feed__rail m-0 flex list-none gap-3 overflow-x-auto p-0 pb-1">
                @foreach ($recent as $media)
                    @php
                        $url = $media->publicUrl();
                    @endphp
                    <li class="media-feed__item w-32 shrink-0">
                        <a href="{{ route('admin.media.edit', $media) }}" class="media-feed__link block" title="{{ $media->filename }}">
                            @if ($url)
                                <img class="aspect-square w-full rounded-lg object-cover" src="{{ $url }}" alt="{{ $media->alt_text ?: $media->filename }}" loading="lazy">
                            @else
                                <span class="media-feed__placeholder flex aspect-square w-full items-center justify-center rounded-lg bg-gray-100 text-xs text-gray-500 dark:bg-gray-700 dark:text-gray-400">No preview</span>
                            @endif
                            <span class="media-feed__caption mt-1.5 block">
                                <span class="media-feed__name block truncate text-xs font-medium text-gray-800 dark:text-gray-200">{{ Str::limit($media->filename, 18) }}</span>
                                <span class="media-feed__when block text-xs text-gray-500 dark:text-gray-400">{{ $media->created_at?->diffForHumans(null, true) }} ago</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </x-ui.card>
    @endif

    <section class="media-toolbar mt-6 space-y-4" aria-label="Filter and search">
        <form method="GET" action="{{ route('admin.media.index') }}" class="media-toolbar__search max-w-none! flex flex-col gap-3 sm:flex-row sm:items-end" role="search">
            <input
                type="hidden"
                name="filter"
                value="{{ $filter }}"
            >
            <div class="flex flex-1 flex-col gap-1.5">
                <label class="sr-only visually-hidden" for="media-search">Search</label>
                <input
                    class="{{ $input }}"
                    id="media-search"
                    type="search"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Search by filename, alt text, or #ID…"
                    autocomplete="off"
                >
            </div>
            <div class="flex items-center gap-3">
                <x-ui.button variant="secondary" type="submit">Search</x-ui.button>
                @if ($search !== '')
                    <a href="{{ route('admin.media.index', ['filter' => $filter]) }}" class="media-toolbar__clear text-sm text-gray-500 underline dark:text-gray-400">Clear</a>
                @endif
            </div>
        </form>

        <nav class="media-filters flex flex-wrap gap-2" aria-label="Library filters">
            @foreach ($filters as $value)
                <a
                    class="{{ $pill }} {{ $filter === $value ? $on : $off }}"
                    href="{{ route('admin.media.index', array_filter(['filter' => $value, 'q' => $search])) }}"
                >{{ $filterLabels[$value] ?? Str::title($value) }}</a>
            @endforeach
        </nav>
    </section>

    @if ($mediaItems->isEmpty())
        <x-ui.card class="media-empty mt-6 text-center">
            <p class="media-empty__title text-base font-semibold text-gray-900 dark:text-white">Nothing matches.</p>
            <p class="media-empty__copy mt-1 text-sm text-gray-500 dark:text-gray-400">
                Try a different filter or
                <a class="{{ $link }}" href="{{ route('admin.media.create') }}">upload a new photo</a>.
            </p>
        </x-ui.card>
    @else
        <section class="media-masonry mt-6 columns-2 gap-4 md:columns-3 xl:columns-4" aria-label="Photo grid">
            @foreach ($mediaItems as $media)
                @php
                    $url = $media->publicUrl();
                    $usage = collect()
                        ->concat($media->weddingStories->map(fn ($s) => [
                            'kind' => 'wedding',
                            'icon' => '💍',
                            'label' => $s->title,
                            'url' => route('admin.wedding-stories.edit', $s),
                        ]))
                        ->concat($media->journalPosts->map(fn ($p) => [
                            'kind' => 'journal',
                            'icon' => '📓',
                            'label' => $p->title,
                            'url' => route('admin.journal-posts.edit', $p),
                        ]))
                        ->concat($media->pages->map(fn ($p) => [
                            'kind' => 'page',
                            'icon' => '📄',
                            'label' => $p->title,
                            'url' => route('admin.pages.edit', $p),
                        ]))
                        ->concat($media->venues->map(fn ($v) => [
                            'kind' => 'venue',
                            'icon' => '📍',
                            'label' => $v->name,
                            'url' => route('admin.venues.edit', $v),
                        ]));
                    $altMissing = blank($media->alt_text);
                    $aspect = ($media->width && $media->height && $media->width > 0)
                        ? round($media->height / $media->width, 4)
                        : 1.25;
                @endphp

                <article class="media-card {{ $altMissing ? 'media-card--needs-alt ring-2 ring-amber-400/60' : '' }} relative mb-4 break-inside-avoid overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800/50" style="--card-aspect: {{ $aspect }};">
                    <a class="media-card__photo block" href="{{ route('admin.media.edit', $media) }}">
                        @if ($url)
                            <img class="h-auto w-full" src="{{ $url }}" alt="{{ $media->alt_text ?: $media->filename }}" loading="lazy">
                        @else
                            <span class="media-card__placeholder flex aspect-[4/3] w-full items-center justify-center bg-gray-100 text-xs text-gray-500 dark:bg-gray-700 dark:text-gray-400">No preview</span>
                        @endif
                    </a>

                    <div class="media-card__overlay pointer-events-none absolute left-2 top-2" aria-hidden="true">
                        <div class="media-card__badges flex flex-wrap gap-1">
                            @if ($altMissing)
                                <x-ui.badge tone="amber" class="media-badge media-badge--alert" title="Missing alt text">alt?</x-ui.badge>
                            @endif
                            @if ($usage->isEmpty())
                                <x-ui.badge tone="gray" class="media-badge media-badge--muted" title="Not attached to anything">orphan</x-ui.badge>
                            @endif
                            @if ($media->original_wp_attachment_id)
                                <x-ui.badge tone="brand" class="media-badge" title="Imported from WordPress">WP</x-ui.badge>
                            @elseif (Str::startsWith($media->path ?? '', 'imports/pictime/'))
                                <x-ui.badge tone="brand" class="media-badge" title="Imported from Pic-Time">Pic-Time</x-ui.badge>
                            @endif
                        </div>
                    </div>

                    <footer class="media-card__body space-y-2 p-3 text-sm">
                        <div class="media-card__meta min-w-0">
                            <strong class="block truncate font-medium text-gray-900 dark:text-white" title="{{ $media->filename }}">{{ Str::limit($media->filename, 24) }}</strong>
                            <span class="text-xs text-gray-500 dark:text-gray-400">#{{ $media->id }}@if ($media->width && $media->height) · {{ $media->width }}×{{ $media->height }}@endif</span>
                        </div>

                        @if ($usage->isNotEmpty())
                            <ul class="media-card__usage m-0 flex list-none flex-wrap gap-1 p-0" title="Used in {{ $usage->count() }} place{{ $usage->count() === 1 ? '' : 's' }}">
                                @foreach ($usage->take(3) as $use)
                                    <li>
                                        <a href="{{ $use['url'] }}" class="media-use-chip media-use-chip--{{ $use['kind'] }} {{ $chip }} hover:bg-gray-200 dark:hover:bg-gray-600" title="{{ $use['label'] }}">
                                            <span aria-hidden="true">{{ $use['icon'] }}</span>
                                            <span class="media-use-chip__label ml-1">{{ Str::limit($use['label'], 18) }}</span>
                                        </a>
                                    </li>
                                @endforeach
                                @if ($usage->count() > 3)
                                    <li class="media-use-chip media-use-chip--more {{ $chip }}">+{{ $usage->count() - 3 }}</li>
                                @endif
                            </ul>
                        @endif

                        <a class="media-card__edit {{ $link }}" href="{{ route('admin.media.edit', $media) }}">Edit</a>
                    </footer>
                </article>
            @endforeach
        </section>

        @if ($mediaItems->hasPages())
            <div class="mt-4">
                {{ $mediaItems->links() }}
            </div>
        @endif
    @endif
@endsection
