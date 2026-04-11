@props([
    'tone' => 'neutral',
    'label' => null,
    'value' => null,
    'description' => null,
])

<article {{ $attributes->class("admin-signal-card admin-signal-card--{$tone}") }}>
    @if ($label)
        <p class="eyebrow">{{ $label }}</p>
    @endif

    @if ($value !== null)
        <strong>{{ $value }}</strong>
    @endif

    @if ($description)
        <span class="meta">{{ $description }}</span>
    @endif

    {{ $slot }}
</article>
