@props(['tone' => 'gray'])
@php
    $tones = [
        'gray' => 'bg-gray-100 text-gray-700 dark:bg-gray-700/60 dark:text-gray-300',
        'brand' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300',
        'green' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        'red' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
    ];
@endphp
<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', $tones[$tone] ?? $tones['gray']]) }}>{{ $slot }}</span>
