<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#111827">
    <link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png">
    <link rel="apple-touch-icon" href="/icon-192.png">
    <link rel="manifest" href="/admin-manifest.json">
    <meta name="vapid-public-key" content="{{ config('services.webpush.public_key') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') | {{ config('app.name') }}</title>
    <script>
        // Set theme before paint to avoid a light/dark flash.
        try {
            var stored = JSON.parse(localStorage.getItem('adminDark'));
            if (stored === true || (stored === null && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        } catch (e) {}
    </script>
    @vite(['resources/css/admin-v2.css', 'resources/js/admin-v2.js'])
</head>
<body
    class="bg-gray-50 font-sans text-gray-800 antialiased dark:bg-gray-900 dark:text-gray-200"
    x-data="{
        sidebarOpen: false,
        collapsed: $persist(false).as('adminSidebarCollapsed'),
        dark: $persist(document.documentElement.classList.contains('dark')).as('adminDark'),
        toggleDark() { this.dark = ! this.dark; document.documentElement.classList.toggle('dark', this.dark); },
    }"
    x-init="document.documentElement.classList.toggle('dark', dark)"
    @keydown.escape.window="sidebarOpen = false"
>
    @include('layouts.admin-v2.sidebar')

    <div class="flex min-h-screen flex-col transition-all duration-200" :class="collapsed ? 'lg:pl-20' : 'lg:pl-72'">
        @include('layouts.admin-v2.header')

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto w-full max-w-7xl">
                @hasSection('heading')
                    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            @hasSection('eyebrow')
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">@yield('eyebrow')</p>
                            @endif
                            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">@yield('heading')</h1>
                            @hasSection('subheading')
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">@yield('subheading')</p>
                            @endif
                        </div>
                        @yield('header_actions')
                    </div>
                @endif

                @if (session('status'))
                    <x-ui.alert class="mb-4">{{ session('status') }}</x-ui.alert>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
