@props(['label', 'value', 'icon' => 'users', 'tone' => 'brand'])
@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300',
        'red' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300',
        'green' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
        'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300',
    ];
@endphp
<div {{ $attributes->class(['flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800/50']) }}>
    <span class="flex size-12 shrink-0 items-center justify-center rounded-xl {{ $tones[$tone] ?? $tones['brand'] }}">
        <x-ui.icon :name="$icon" class="size-6" />
    </span>
    <div>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $value }}</p>
    </div>
</div>
