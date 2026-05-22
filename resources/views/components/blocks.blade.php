@props([
    'blocks' => [],
])

@foreach ($blocks as $block)
    <x-block :block="$block" />
@endforeach
