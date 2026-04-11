@props([
    'label' => null,
    'value' => null,
    'meta' => null,
])

<article {{ $attributes->class('admin-card admin-card--metric') }}>
    @if ($label)
        <p class="eyebrow">{{ $label }}</p>
    @endif

    <p class="admin-stat">{{ $value ?? $slot }}</p>

    @if ($meta)
        <p class="meta">{{ $meta }}</p>
    @endif
</article>
