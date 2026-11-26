@props(['name', 'class' => 'size-5'])
@php
    static $cache = [];
    $path = resource_path('svg/heroicons/'.$name.'.svg');
    $cache[$name] ??= is_file($path) ? trim(file_get_contents($path)) : '';
    $svg = preg_replace('/<svg /', '<svg class="'.e($class).'" ', $cache[$name], 1);
@endphp
{!! $svg !!}
