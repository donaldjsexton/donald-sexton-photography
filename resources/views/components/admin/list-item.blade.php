@props([
    'title' => null,
    'meta' => null,
])

<div {{ $attributes->class('admin-list__item') }}>
    @if ($title)
        <strong>{{ $title }}</strong>
    @endif

    @if ($meta)
        <span class="meta">{{ $meta }}</span>
    @endif

    {{ $slot }}
</div>
