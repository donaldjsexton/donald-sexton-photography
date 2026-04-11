@props([
    'href' => '#',
    'label' => null,
    'description' => null,
])

<a {{ $attributes->class('admin-action-card') }} href="{{ $href }}">
    @if ($label)
        <strong>{{ $label }}</strong>
    @endif

    @if ($description)
        <span class="meta">{{ $description }}</span>
    @endif

    {{ $slot }}
</a>
