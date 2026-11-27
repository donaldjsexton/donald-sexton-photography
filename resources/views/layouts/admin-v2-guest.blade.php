<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#111827">
    <meta name="robots" content="noindex">
    <link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png">
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
        dark: $persist(document.documentElement.classList.contains('dark')).as('adminDark'),
        toggleDark() { this.dark = ! this.dark; document.documentElement.classList.toggle('dark', this.dark); },
    }"
    x-init="document.documentElement.classList.toggle('dark', dark)"
>
    <button type="button" @click="toggleDark()" aria-label="Toggle dark mode"
        class="fixed right-4 top-4 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-600 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
        <span x-text="dark ? 'Light' : 'Dark'">Dark</span>
    </button>
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-md">
            @yield('content')
        </div>
    </main>
</body>
</html>
