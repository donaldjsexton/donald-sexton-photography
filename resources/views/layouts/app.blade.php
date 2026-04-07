<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $metaTitle = trim($__env->yieldContent('title', 'Donald Sexton Photography'));
        $metaDescription = trim($__env->yieldContent('meta_description', ''));
        $canonicalUrl = trim($__env->yieldContent('canonical_url', url()->current()));
    @endphp
    <title>{{ $metaTitle }}</title>
    @if ($metaDescription !== '')
        <meta name="description" content="{{ $metaDescription }}">
    @endif
    @if ($canonicalUrl !== '')
        <link rel="canonical" href="{{ $canonicalUrl }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $metaTitle }}">
    @if ($metaDescription !== '')
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta name="twitter:description" content="{{ $metaDescription }}">
    @endif
    @if ($canonicalUrl !== '')
        <meta property="og:url" content="{{ $canonicalUrl }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cormorant-garamond:400,500,600,700|jost:300,400,500,600" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="@yield('body_class')">
    <header class="site-header" data-nav-root>
        <div class="site-header__bar">
            <div class="brand-lockup">
                <strong class="brand-mark"><a href="{{ route('home') }}">Donald Sexton Photography</a></strong>
                <span class="brand-note">Calm wedding photography for Clearwater, Tampa, and beyond.</span>
            </div>
            <button
                class="site-nav__toggle"
                type="button"
                aria-expanded="false"
                aria-controls="site-nav"
                data-nav-toggle
            >
                <span class="site-nav__toggle-label">Menu</span>
                <span class="site-nav__toggle-lines" aria-hidden="true">
                    <span></span>
                    <span></span>
                </span>
            </button>
            <nav class="site-nav" id="site-nav" data-nav-panel>
                <a href="{{ route('weddings.index') }}">Weddings</a>
                <a href="{{ route('collections.index') }}">Collections</a>
                <a href="{{ route('journal.index') }}">Journal</a>
                <a href="{{ route('inquiry.create') }}">Check Availability</a>
            </nav>
        </div>
    </header>

    <main class="site-main">
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="shell site-footer__bar">
            <p>Donald Sexton Photography</p>
            <p>Calm wedding photography for Clearwater, Tampa, and wherever your people gather.</p>
        </div>
    </footer>
</body>
</html>
