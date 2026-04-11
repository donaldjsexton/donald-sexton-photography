@props([
    'copy' => null,
])

<div {{ $attributes->class('admin-toolbar') }}>
    @if ($copy)
        <p class="section-copy">{{ $copy }}</p>
    @endif

    {{ $slot }}
</div>
