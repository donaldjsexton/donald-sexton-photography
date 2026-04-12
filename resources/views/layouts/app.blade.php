<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $defaultDescription = 'Calm wedding photography for Clearwater, Tampa, and beyond.';
        $metaTitle = trim($__env->yieldContent('title', 'Donald Sexton Photography'));
        $metaDescription = trim($__env->yieldContent('meta_description', $defaultDescription));
        $canonicalUrl = trim($__env->yieldContent('canonical_url', url()->current()));
        $siteName = config('app.name', 'Donald Sexton Photography');
        $siteUrl = rtrim(config('app.url', url('/')), '/');
        $websiteSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => $siteUrl,
        ];
        $businessSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'ProfessionalService',
            'name' => $siteName,
            'url' => $siteUrl,
            'description' => $defaultDescription,
            'areaServed' => ['Clearwater', 'Tampa', 'Florida'],
        ];
    @endphp
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    @if ($canonicalUrl !== '')
        <link rel="canonical" href="{{ $canonicalUrl }}">
    @endif
    <meta name="robots" content="index,follow,max-image-preview:large">
    <meta name="author" content="Donald Sexton Photography">
    <meta name="theme-color" content="#f9f7f4">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:locale" content="en_US">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    @if ($canonicalUrl !== '')
        <meta property="og:url" content="{{ $canonicalUrl }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cormorant-garamond:400,500,600,700|jost:300,400,500,600" rel="stylesheet" />
    <script type="application/ld+json">{!! json_encode($websiteSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/ld+json">{!! json_encode($businessSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @if ($analyticsMeasurementId)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $analyticsMeasurementId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ $analyticsMeasurementId }}');
        </script>
    @endif
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
        <nav class="shell site-footer__nav" aria-label="Footer">
            <a href="{{ route('weddings.index') }}">Weddings</a>
            <a href="{{ route('collections.index') }}">Collections</a>
            <a href="{{ route('journal.index') }}">Journal</a>
            <a href="{{ route('inquiry.create') }}">Inquire</a>
        </nav>
        <div class="shell site-footer__bar">
            <p>Donald Sexton Photography</p>
            <p>Calm wedding photography for Clearwater, Tampa, and wherever your people gather.</p>
        </div>
    </footer>

    @unless (request()->routeIs('inquiry.create', 'inquiry.thank-you'))
        <div class="sticky-cta" data-sticky-cta>
            <a class="sticky-cta__link" href="{{ route('inquiry.create') }}">Check Availability</a>
        </div>
    @endunless
</body>
</html>
