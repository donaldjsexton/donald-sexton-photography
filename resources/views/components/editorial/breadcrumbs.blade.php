@props([
    'items' => [],
])

@php
    $crumbs = collect($items)
        ->map(fn ($item) => is_array($item)
            ? ['name' => trim((string) ($item['name'] ?? '')), 'url' => trim((string) ($item['url'] ?? ''))]
            : ['name' => trim((string) $item), 'url' => ''])
        ->filter(fn (array $item) => $item['name'] !== '')
        ->values();
@endphp

@if ($crumbs->isNotEmpty())
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <div class="page-shell--wide">
            <ol class="breadcrumbs__list">
                @foreach ($crumbs as $index => $crumb)
                    <li class="breadcrumbs__item">
                        @if ($crumb['url'] !== '' && $index < $crumbs->count() - 1)
                            <a class="breadcrumbs__link" href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>
                        @else
                            <span class="breadcrumbs__current" aria-current="page">{{ $crumb['name'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    </nav>
@endif
