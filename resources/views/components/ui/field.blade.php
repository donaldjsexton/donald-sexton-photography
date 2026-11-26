@props(['label', 'name' => null])
<label {{ $attributes->class(['flex flex-col gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-300']) }}>
    {{ $label }}
    {{ $slot }}
</label>
