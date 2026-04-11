@props([
    'eyebrow' => null,
    'title' => null,
    'description' => null,
])

<header {{ $attributes->class('admin-section-header') }}>
    @if ($eyebrow)
        <p class="eyebrow">{{ $eyebrow }}</p>
    @endif

    @if ($title)
        <h3 class="admin-section-header__title">{{ $title }}</h3>
    @endif

    @if ($description)
        <p class="section-copy admin-section-header__description">{{ $description }}</p>
    @endif

    {{ $slot }}
</header>
