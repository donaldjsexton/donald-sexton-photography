<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin')</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="admin-page">
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <div class="admin-brand">
                <p class="eyebrow">Editorial CMS</p>
                <h1>Donald Sexton</h1>
                <p class="meta">Media, content, and import tools.</p>
            </div>

            <nav class="admin-nav">
                <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                <a href="{{ route('admin.homepage.edit') }}">Homepage</a>
                <a href="{{ route('admin.media.index') }}">Media</a>
                <a href="{{ route('admin.pages.index') }}">Pages</a>
                <a href="{{ route('admin.wedding-stories.index') }}">Wedding Stories</a>
                <a href="{{ route('admin.journal-posts.index') }}">Journal Posts</a>
                <a href="{{ route('admin.imports.wordpress.index') }}">Legacy Blog Import</a>
                <a href="{{ route('admin.imports.pictime.index') }}">Pic-Time Import</a>
            </nav>
        </aside>

        <div class="admin-main">
            <header class="admin-header">
                <div>
                    <p class="eyebrow">@yield('eyebrow', 'Admin')</p>
                    <h2>@yield('heading', 'Dashboard')</h2>
                </div>

                <div class="admin-header__actions">
                    <span class="meta">{{ auth()->user()?->email }}</span>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button class="cta-secondary" type="submit">Log Out</button>
                    </form>
                </div>
            </header>

            <main class="admin-content">
                @if (session('status'))
                    <div class="admin-flash">{{ session('status') }}</div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
