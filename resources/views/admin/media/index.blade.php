@extends('layouts.admin')

@section('title', 'Media')
@section('heading', 'Media Library')
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
    @endphp

    <section class="media-hero">
        <div class="media-hero__head">
            <div>
                <p class="eyebrow">Studio</p>
                <h2 class="media-hero__title">Photo library</h2>
                <p class="media-hero__tagline">Every image used across hero areas, stories, journal posts, pages, and venues.</p>
            </div>
            <a class="cta" href="{{ route('admin.media.create') }}">＋ Upload photo</a>
        </div>

        <div class="media-stats">
            <a class="media-stat {{ $filter === 'all' ? 'is-active' : '' }}" href="{{ route('admin.media.index') }}">
                <span class="media-stat__value">{{ number_format($stats['total']) }}</span>
                <span class="media-stat__label">Total</span>
            </a>
            <a class="media-stat" href="{{ route('admin.media.index') }}#used">
                <span class="media-stat__value">{{ number_format($stats['used']) }}</span>
                <span class="media-stat__label">In use</span>
            </a>
            <a class="media-stat {{ $filter === 'unused' ? 'is-active' : '' }}" href="{{ route('admin.media.index', ['filter' => 'unused']) }}">
                <span class="media-stat__value">{{ number_format($stats['orphaned']) }}</span>
                <span class="media-stat__label">Orphaned</span>
            </a>
            <a class="media-stat {{ $stats['missing_alt'] > 0 ? 'media-stat--alert' : 'media-stat--ok' }} {{ $filter === 'missing-alt' ? 'is-active' : '' }}"
               href="{{ route('admin.media.index', ['filter' => 'missing-alt']) }}">
                <span class="media-stat__value">{{ $stats['missing_alt'] > 0 ? number_format($stats['missing_alt']) : '✓' }}</span>
                <span class="media-stat__label">Missing alt</span>
            </a>
            <a class="media-stat {{ $filter === 'recent' ? 'is-active' : '' }}" href="{{ route('admin.media.index', ['filter' => 'recent']) }}">
                <span class="media-stat__value">{{ number_format($stats['this_month']) }}</span>
                <span class="media-stat__label">This month</span>
            </a>
        </div>
    </section>

    @if ($recent->isNotEmpty() && $filter === 'all' && $search === '')
        <section class="media-feed" aria-label="Recently added photos">
            <header class="media-feed__head">
                <h3 class="media-feed__title">Fresh uploads</h3>
                <span class="media-feed__meta">Latest {{ $recent->count() }}</span>
            </header>
            <ol class="media-feed__rail">
                @foreach ($recent as $media)
                    @php
                        $url = $media->publicUrl();
                    @endphp
                    <li class="media-feed__item">
                        <a href="{{ route('admin.media.edit', $media) }}" class="media-feed__link" title="{{ $media->filename }}">
                            @if ($url)
                                <img src="{{ $url }}" alt="{{ $media->alt_text ?: $media->filename }}" loading="lazy">
                            @else
                                <span class="media-feed__placeholder">No preview</span>
                            @endif
                            <span class="media-feed__caption">
                                <span class="media-feed__name">{{ Str::limit($media->filename, 18) }}</span>
                                <span class="media-feed__when">{{ $media->created_at?->diffForHumans(null, true) }} ago</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="media-toolbar" aria-label="Filter and search">
        <form method="GET" action="{{ route('admin.media.index') }}" class="media-toolbar__search" role="search">
            <input
                type="hidden"
                name="filter"
                value="{{ $filter }}"
            >
            <label class="visually-hidden" for="media-search">Search</label>
            <input
                id="media-search"
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="Search by filename, alt text, or #ID…"
                autocomplete="off"
            >
            <button type="submit" class="cta-secondary">Search</button>
            @if ($search !== '')
                <a href="{{ route('admin.media.index', ['filter' => $filter]) }}" class="media-toolbar__clear">Clear</a>
            @endif
        </form>

        <nav class="media-filters" aria-label="Library filters">
            @foreach ($filters as $value)
                <a
                    class="media-filter {{ $filter === $value ? 'is-active' : '' }}"
                    href="{{ route('admin.media.index', array_filter(['filter' => $value, 'q' => $search])) }}"
                >{{ $filterLabels[$value] ?? Str::title($value) }}</a>
            @endforeach
        </nav>
    </section>

    @if ($mediaItems->isEmpty())
        <section class="media-empty">
            <p class="media-empty__title">Nothing matches.</p>
            <p class="media-empty__copy">
                Try a different filter or
                <a href="{{ route('admin.media.create') }}">upload a new photo</a>.
            </p>
        </section>
    @else
        <section class="media-masonry" aria-label="Photo grid">
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

                <article class="media-card {{ $altMissing ? 'media-card--needs-alt' : '' }}" style="--card-aspect: {{ $aspect }};">
                    <a class="media-card__photo" href="{{ route('admin.media.edit', $media) }}">
                        @if ($url)
                            <img src="{{ $url }}" alt="{{ $media->alt_text ?: $media->filename }}" loading="lazy">
                        @else
                            <span class="media-card__placeholder">No preview</span>
                        @endif
                    </a>

                    <div class="media-card__overlay" aria-hidden="true">
                        <div class="media-card__badges">
                            @if ($altMissing)
                                <span class="media-badge media-badge--alert" title="Missing alt text">alt?</span>
                            @endif
                            @if ($usage->isEmpty())
                                <span class="media-badge media-badge--muted" title="Not attached to anything">orphan</span>
                            @endif
                            @if ($media->original_wp_attachment_id)
                                <span class="media-badge" title="Imported from WordPress">WP</span>
                            @elseif (Str::startsWith($media->path ?? '', 'imports/pictime/'))
                                <span class="media-badge" title="Imported from Pic-Time">Pic-Time</span>
                            @endif
                        </div>
                    </div>

                    <footer class="media-card__body">
                        <div class="media-card__meta">
                            <strong title="{{ $media->filename }}">{{ Str::limit($media->filename, 24) }}</strong>
                            <span class="meta">#{{ $media->id }}@if ($media->width && $media->height) · {{ $media->width }}×{{ $media->height }}@endif</span>
                        </div>

                        @if ($usage->isNotEmpty())
                            <ul class="media-card__usage" title="Used in {{ $usage->count() }} place{{ $usage->count() === 1 ? '' : 's' }}">
                                @foreach ($usage->take(3) as $use)
                                    <li>
                                        <a href="{{ $use['url'] }}" class="media-use-chip media-use-chip--{{ $use['kind'] }}" title="{{ $use['label'] }}">
                                            <span aria-hidden="true">{{ $use['icon'] }}</span>
                                            <span class="media-use-chip__label">{{ Str::limit($use['label'], 18) }}</span>
                                        </a>
                                    </li>
                                @endforeach
                                @if ($usage->count() > 3)
                                    <li class="media-use-chip media-use-chip--more">+{{ $usage->count() - 3 }}</li>
                                @endif
                            </ul>
                        @endif

                        <a class="media-card__edit" href="{{ route('admin.media.edit', $media) }}">Edit</a>
                    </footer>
                </article>
            @endforeach
        </section>

        @if ($mediaItems->hasPages())
            <div class="pagination">
                {{ $mediaItems->links() }}
            </div>
        @endif
    @endif
@endsection
