@php
    $navigationGroups = [
        [
            'label' => 'Main',
            'items' => [
                ['label' => 'Dashboard', 'icon' => 'squares-2x2', 'href' => route('admin.dashboard'), 'patterns' => ['admin.dashboard']],
                ['label' => 'Inquiries', 'icon' => 'inbox', 'href' => route('admin.inquiries.index'), 'patterns' => ['admin.inquiries.*']],
                ['label' => 'Calendar', 'icon' => 'calendar-days', 'href' => route('admin.booked-jobs.index'), 'patterns' => ['admin.booked-jobs.*']],
                ['label' => 'Contacts', 'icon' => 'users', 'href' => route('admin.contacts.index'), 'patterns' => ['admin.contacts.*']],
                ['label' => 'Clients', 'icon' => 'user-group', 'href' => route('admin.clients.index'), 'patterns' => ['admin.clients.*']],
                ['label' => 'Invoices', 'icon' => 'currency-dollar', 'href' => route('admin.invoices.index'), 'patterns' => ['admin.invoices.*']],
                ['label' => 'Contracts', 'icon' => 'document-text', 'href' => route('admin.contracts.index'), 'patterns' => ['admin.contracts.*', 'admin.contract-templates.*']],
            ],
        ],
        [
            'label' => 'Content',
            'items' => [
                ['label' => 'Homepage', 'icon' => 'home', 'href' => route('admin.homepage.edit'), 'patterns' => ['admin.homepage.*']],
                ['label' => 'Wedding Stories', 'icon' => 'photo', 'href' => route('admin.wedding-stories.index'), 'patterns' => ['admin.wedding-stories.*']],
                ['label' => 'Journal Posts', 'icon' => 'book-open', 'href' => route('admin.journal-posts.index'), 'patterns' => ['admin.journal-posts.*']],
                ['label' => 'Venues', 'icon' => 'map-pin', 'href' => route('admin.venues.index'), 'patterns' => ['admin.venues.*']],
                ['label' => 'Pages', 'icon' => 'document-duplicate', 'href' => route('admin.pages.index'), 'patterns' => ['admin.pages.*']],
                ['label' => 'Media', 'icon' => 'photo', 'href' => route('admin.media.index'), 'patterns' => ['admin.media.*']],
                ['label' => 'Galleries', 'icon' => 'building-storefront', 'href' => route('admin.galleries.index'), 'patterns' => ['admin.galleries.*']],
            ],
        ],
        [
            'label' => 'More',
            'items' => [
                ['label' => 'Settings', 'icon' => 'cog-6-tooth', 'href' => route('admin.settings.edit'), 'patterns' => ['admin.settings.*']],
                ['label' => 'Logs', 'icon' => 'list-bullet', 'href' => route('admin.logs.index'), 'patterns' => ['admin.logs.*']],
            ],
        ],
    ];
@endphp

{{-- Mobile backdrop --}}
<div x-show="sidebarOpen" x-cloak x-transition.opacity @click="sidebarOpen = false"
     class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden"></div>

<aside
    class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-gray-200 bg-white transition-all duration-200 dark:border-gray-800 dark:bg-gray-900 lg:translate-x-0"
    :class="[sidebarOpen ? 'translate-x-0' : '', collapsed ? 'lg:w-20' : 'lg:w-72']"
>
    <div class="flex h-16 shrink-0 items-center gap-3 px-5">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-500 text-sm font-bold text-white">DS</span>
        <span class="truncate text-base font-semibold text-gray-900 dark:text-white" :class="collapsed && 'lg:hidden'">Donald Sexton</span>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
        @foreach ($navigationGroups as $group)
            <div>
                <p class="mb-2 px-3 text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500"
                   :class="collapsed && 'lg:hidden'">{{ $group['label'] }}</p>
                <ul class="space-y-1">
                    @foreach ($group['items'] as $item)
                        @php $active = request()->routeIs(...$item['patterns']); @endphp
                        <li>
                            <a href="{{ $item['href'] }}" title="{{ $item['label'] }}"
                               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors
                                      {{ $active
                                          ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300'
                                          : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white' }}"
                               :class="collapsed && 'lg:justify-center'">
                                <x-ui.icon :name="$item['icon']" class="size-5 shrink-0" />
                                <span class="truncate" :class="collapsed && 'lg:hidden'">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    <div class="border-t border-gray-200 p-3 dark:border-gray-800" :class="collapsed && 'lg:hidden'">
        <button type="button" id="push-toggle" disabled
                class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 disabled:opacity-60 dark:text-gray-400 dark:hover:bg-gray-800 [&.is-subscribed]:text-emerald-600 dark:[&.is-subscribed]:text-emerald-400">
            <x-ui.icon name="bell" class="size-5 shrink-0" />
            <span id="push-label" class="truncate">Checking notifications…</span>
        </button>
    </div>
</aside>
