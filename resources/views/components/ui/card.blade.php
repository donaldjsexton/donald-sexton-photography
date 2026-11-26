@props(['title' => null, 'flush' => false])
<section {{ $attributes->class(['rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800/50']) }}>
    @if ($title || isset($actions))
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
            @if ($title)<h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>@endif
            {{ $actions ?? '' }}
        </div>
    @endif
    <div @class(['p-5' => ! $flush])>{{ $slot }}</div>
</section>
