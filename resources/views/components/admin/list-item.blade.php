@props([
    'title' => null,
    'meta' => null,
    'href' => null,
])

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class('admin-list__item admin-list__item--link') }}>
        @if ($title)
            <strong>{{ $title }}</strong>
        @endif

        @if ($meta)
            <span class="meta">{{ $meta }}</span>
        @endif

        {{ $slot }}
    </a>
@else
    <div {{ $attributes->class('admin-list__item') }}>
        @if ($title)
            <strong>{{ $title }}</strong>
        @endif

        @if ($meta)
            <span class="meta">{{ $meta }}</span>
        @endif

        {{ $slot }}
    </div>
@endif
