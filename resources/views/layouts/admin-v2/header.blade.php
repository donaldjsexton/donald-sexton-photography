<header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-gray-200 bg-white/90 px-4 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90 sm:px-6 lg:px-8">
    <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 lg:hidden"
            @click="sidebarOpen = true" aria-label="Open menu">
        <x-ui.icon name="bars-3" class="size-6" />
    </button>
    <button type="button" class="hidden rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 lg:block"
            @click="collapsed = ! collapsed" aria-label="Toggle sidebar">
        <x-ui.icon name="bars-3" class="size-6" />
    </button>

    <div class="ml-auto flex items-center gap-2">
        <button type="button" @click="toggleDark()" aria-label="Toggle dark mode"
                class="rounded-full p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800">
            <span x-show="! dark"><x-ui.icon name="moon" class="size-5" /></span>
            <span x-show="dark" x-cloak><x-ui.icon name="sun" class="size-5" /></span>
        </button>

        @php $email = auth()->user()?->email; @endphp
        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button type="button" @click="open = ! open"
                    class="flex size-9 items-center justify-center rounded-full bg-brand-500 text-sm font-semibold text-white"
                    aria-label="Account menu">{{ strtoupper(substr($email ?: 'A', 0, 1)) }}</button>
            <div x-show="open" x-cloak x-transition
                 class="absolute right-0 mt-2 w-60 rounded-xl border border-gray-200 bg-white p-2 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                <p class="truncate px-3 py-2 text-xs text-gray-500 dark:text-gray-400">{{ $email }}</p>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit"
                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                        <x-ui.icon name="arrow-right-on-rectangle" class="size-5" /> Log out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
