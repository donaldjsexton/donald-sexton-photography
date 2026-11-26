@props(['variant' => 'primary', 'href' => null, 'type' => 'button'])
@php
    $variants = [
        'primary' => 'bg-brand-500 text-white hover:bg-brand-600',
        'secondary' => 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-700',
    ];
    $classes = 'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium shadow-sm transition-colors '.($variants[$variant] ?? $variants['primary']);
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$classes]) }}>{{ $slot }}</button>
@endif
