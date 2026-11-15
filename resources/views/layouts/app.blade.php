<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        // Inline `@section('name', $value)` runs $value through e() at startSection time,
        // so yieldContent here returns HTML-escaped strings. Emit them with {!! !!} to avoid
        // double-encoding (e.g. `&` becoming `&amp;amp;` and rendering as literal `&amp;`).
        $defaultDescription = 'Calm wedding photography for Clearwater, Tampa, and beyond.';
        $siteName = config('app.name', 'Donald Sexton Photography');
        // Per-record SEO titles are stored without a brand suffix; append it in
        // one place so every page ends "… | Donald Sexton Photography". The
        // helper is idempotent, so pages that already include the brand (home,
        // legal) are left untouched. yieldContent escapes via e(), so decode
        // back to plain text before formatting and re-escape when emitting.
        $rawTitle = htmlspecialchars_decode(trim($__env->yieldContent('title', $siteName)), ENT_QUOTES | ENT_HTML5);
        $metaTitle = e(\App\Support\MetaTitle::format($rawTitle, $siteName));
        $metaDescription = trim($__env->yieldContent('meta_description', $defaultDescription));
        $canonicalUrl = trim($__env->yieldContent('canonical_url', url()->current()));
        $siteUrl = rtrim(config('app.url', url('/')), '/');
        $ogType = trim($__env->yieldContent('og_type', 'website')) ?: 'website';
        // An explicit empty section means "no page image", so fall through to
        // the configured default. Legacy WordPress share paths are rewritten
        // to the copy on the public media disk before the URL is absolutized.
        $yieldedOgImage = trim($__env->yieldContent('og_image'));
        $decodedOgImage = $yieldedOgImage === ''
            ? ''
            : htmlspecialchars_decode($yieldedOgImage, ENT_QUOTES | ENT_HTML5);
        $rawOgImage = \App\Support\ShareImage::resolve($decodedOgImage);
        $ogImage = '';
        if ($rawOgImage !== '') {
            $ogImage = e(preg_match('#^https?://#i', $rawOgImage)
                ? $rawOgImage
                : url($rawOgImage));
        }
        $ogImageAlt = trim($__env->yieldContent('og_image_alt', $siteName));
        $ogArticlePublishedTime = trim($__env->yieldContent('og_article_published_time'));
        $ogArticleModifiedTime = trim($__env->yieldContent('og_article_modified_time'));
        $ogArticleAuthor = trim($__env->yieldContent('og_article_author'));
        $websiteSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => $siteUrl,
            'publisher' => ['@id' => \App\Support\StructuredData::organizationId()],
        ];
        $businessSchema = \App\Support\StructuredData::organization();
        $robots = trim(htmlspecialchars_decode(trim($__env->yieldContent('robots')), ENT_QUOTES | ENT_HTML5));

        if ($robots === '') {
            $robots = 'index,follow,max-image-preview:large';
        }
    @endphp
    <title>{!! $metaTitle !!}</title>
    <meta name="description" content="{!! $metaDescription !!}">
    @if ($canonicalUrl !== '')
        <link rel="canonical" href="{!! $canonicalUrl !!}">
    @endif
    <meta name="robots" content="{{ $robots }}">
    <meta name="author" content="Donald Sexton Photography">
    @foreach (($siteSettings?->verificationMetas() ?? []) as $name => $content)
        <meta name="{{ $name }}" content="{{ $content }}">
    @endforeach
    <meta name="theme-color" content="#f9f7f4">
    <meta property="og:type" content="{!! $ogType !!}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:locale" content="en_US">
    <meta property="og:title" content="{!! $metaTitle !!}">
    <meta property="og:description" content="{!! $metaDescription !!}">
    <meta name="twitter:description" content="{!! $metaDescription !!}">
    @if ($canonicalUrl !== '')
        <meta property="og:url" content="{!! $canonicalUrl !!}">
    @endif
    @if ($ogImage !== '')
        <meta property="og:image" content="{!! $ogImage !!}">
        @if ($ogImageAlt !== '')
            <meta property="og:image:alt" content="{!! $ogImageAlt !!}">
        @endif
        <meta name="twitter:image" content="{!! $ogImage !!}">
        @if ($ogImageAlt !== '')
            <meta name="twitter:image:alt" content="{!! $ogImageAlt !!}">
        @endif
    @endif
    @if ($ogType === 'article')
        @if ($ogArticlePublishedTime !== '')
            <meta property="article:published_time" content="{!! $ogArticlePublishedTime !!}">
        @endif
        @if ($ogArticleModifiedTime !== '')
            <meta property="article:modified_time" content="{!! $ogArticleModifiedTime !!}">
        @endif
        @if ($ogArticleAuthor !== '')
            <meta property="article:author" content="{!! $ogArticleAuthor !!}">
        @endif
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{!! $metaTitle !!}">
    <link rel="alternate" type="application/atom+xml" title="{{ $siteName }} — Journal" href="{{ route('journal.feed') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icon-192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('icon-512.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icon-192.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    @stack('head_preload')
    {{-- Hero and header are Cormorant. Preload the latin files so the swap
         happens against the metric-adjusted fallback instead of after first paint. --}}
    <link rel="preload" href="{{ asset('fonts/cormorant-garamond-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/cormorant-garamond-latin-500-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <script type="application/ld+json">{!! json_encode($websiteSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/ld+json">{!! json_encode($businessSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @stack('json_ld')
    @if ($analyticsMeasurementId)
        {{-- Queue analytics immediately so custom events (availability_date_select,
             inquiry steps) land in dataLayer before gtag.js arrives. Download the
             library on the first real interaction, or a few seconds after load,
             so it stays off the initial paint. Scroll and idle callbacks are
             omitted because a performance audit fires both and would still pay
             the download during measurement. --}}
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ $analyticsMeasurementId }}');
            (function () {
                var loaded = false;
                var events = ['pointerdown', 'keydown', 'touchstart'];
                function load() {
                    if (loaded) { return; }
                    loaded = true;
                    events.forEach(function (name) {
                        window.removeEventListener(name, load, true);
                    });
                    var script = document.createElement('script');
                    script.async = true;
                    script.src = 'https://www.googletagmanager.com/gtag/js?id={{ $analyticsMeasurementId }}';
                    document.head.appendChild(script);
                }
                events.forEach(function (name) {
                    window.addEventListener(name, load, { capture: true, passive: true });
                });
                window.addEventListener('load', function () {
                    window.setTimeout(load, 5000);
                });
            })();
        </script>
    @endif
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="@yield('body_class')">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <header class="site-header" data-nav-root>
        <div class="site-header__bar">
            <div class="brand-lockup">
                <strong class="brand-mark"><a href="{{ route('home') }}">Donald Sexton Photography</a></strong>
                <span class="brand-note">Calm wedding photography for Clearwater, Tampa, and beyond.</span>
            </div>
            <button
                class="site-nav__toggle"
                type="button"
                aria-label="Menu"
                aria-expanded="false"
                aria-controls="site-nav"
                data-nav-toggle
            >
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

    <main class="site-main" id="main-content" tabindex="-1">
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
        <nav class="shell site-footer__legal" aria-label="Legal">
            <a href="{{ route('legal.privacy') }}">Privacy Policy</a>
            <a href="{{ route('legal.terms') }}">Terms of Service</a>
        </nav>
    </footer>

    @unless (request()->routeIs('inquiry.create', 'inquiry.thank-you', 'questionnaire.*'))
        <div class="sticky-cta" data-sticky-cta>
            <a class="sticky-cta__link" href="{{ route('inquiry.create') }}">Check Availability</a>
        </div>
    @endunless
</body>
</html>
