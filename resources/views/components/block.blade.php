@props([
    'block',
])

@php
    $slug = strtolower(str_replace('_', '-', (string) $block->type));
    $component = 'block-types.'.$slug;
    $resolved = view()->exists('components.'.$component) ? $component : 'block-types.rich-text';
@endphp

<x-dynamic-component :component="$resolved" :block="$block" />
