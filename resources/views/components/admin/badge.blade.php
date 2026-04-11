@props([
    'tone' => null,
])

@php
    $badgeClasses = collect(['admin-status-pill'])
        ->when(
            $tone,
            fn ($classes) => $classes->push('admin-status-pill--'.str_replace('_', '-', (string) $tone))
        )
        ->implode(' ');
@endphp

<span {{ $attributes->class($badgeClasses) }}>{{ $slot }}</span>
