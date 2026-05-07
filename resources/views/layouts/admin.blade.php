<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#2d1d15">
    <link rel="manifest" href="/manifest.json">
    <meta name="vapid-public-key" content="{{ config('services.webpush.public_key') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin')</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="admin-page">
    @php
        $navigationGroups = [
            [
                'label' => 'Studio',
                'items' => [
                    ['label' => 'Dashboard', 'href' => route('admin.dashboard'), 'patterns' => ['admin.dashboard']],
                    ['label' => 'Inquiries', 'href' => route('admin.inquiries.index'), 'patterns' => ['admin.inquiries.*']],
                    ['label' => 'Booked Jobs', 'href' => route('admin.booked-jobs.index'), 'patterns' => ['admin.booked-jobs.*']],
                    ['label' => 'Clients', 'href' => route('admin.clients.index'), 'patterns' => ['admin.clients.*']],
                    ['label' => 'Invoices', 'href' => route('admin.invoices.index'), 'patterns' => ['admin.invoices.*']],
                ],
            ],
            [
                'label' => 'Content',
                'items' => [
                    ['label' => 'Homepage', 'href' => route('admin.homepage.edit'), 'patterns' => ['admin.homepage.*']],
                    ['label' => 'Wedding Stories', 'href' => route('admin.wedding-stories.index'), 'patterns' => ['admin.wedding-stories.*']],
                    ['label' => 'Journal Posts', 'href' => route('admin.journal-posts.index'), 'patterns' => ['admin.journal-posts.*']],
                    ['label' => 'Venues', 'href' => route('admin.venues.index'), 'patterns' => ['admin.venues.*']],
                    ['label' => 'Pages', 'href' => route('admin.pages.index'), 'patterns' => ['admin.pages.*']],
                    ['label' => 'Media', 'href' => route('admin.media.index'), 'patterns' => ['admin.media.*']],
                ],
            ],
            [
                'label' => 'Operations',
                'items' => [
                    ['label' => 'Settings', 'href' => route('admin.settings.edit'), 'patterns' => ['admin.settings.*']],
                    ['label' => 'Imports', 'href' => route('admin.imports.index'), 'patterns' => ['admin.imports.*']],
                    ['label' => 'Console', 'href' => route('admin.console.index'), 'patterns' => ['admin.console.*']],
                    ['label' => 'Logs', 'href' => route('admin.logs.index'), 'patterns' => ['admin.logs.*']],
                ],
            ],
        ];
    @endphp

    <div class="admin-drawer-backdrop" id="drawer-backdrop"></div>

    <div class="admin-shell">
        <aside class="admin-sidebar" id="admin-sidebar">
            <div class="admin-sidebar__surface">
                <div class="admin-brand">
                    <p class="eyebrow">Site Admin</p>
                    <h1>Donald Sexton Photography</h1>
                    <p class="meta">Pages, stories, media, and site settings.</p>
                </div>

                <div class="admin-sidebar__status">
                    <p class="eyebrow">Site Status</p>
                    <strong>{{ $analyticsMeasurementId ? 'Analytics connected' : 'Analytics not set' }}</strong>
                    <span class="meta">{{ $analyticsMeasurementId ?: 'Add a GA4 measurement ID in Settings.' }}</span>
                </div>

                <div class="admin-sidebar__status" id="push-status">
                    <button type="button" id="push-toggle" class="push-toggle" disabled>
                        <span id="push-label">Checking notifications…</span>
                    </button>
                </div>

                @foreach ($navigationGroups as $group)
                    <div class="admin-nav-group">
                        <p class="admin-nav-group__label">{{ $group['label'] }}</p>

                        <nav class="admin-nav">
                            @foreach ($group['items'] as $item)
                                <a
                                    href="{{ $item['href'] }}"
                                    class="{{ request()->routeIs(...$item['patterns']) ? 'is-active' : '' }}"
                                >
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </nav>
                    </div>
                @endforeach

                <div class="admin-sidebar__logout">
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button class="cta-secondary" type="submit">Log Out</button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="admin-main">
            <header class="admin-header">
                <button type="button" class="admin-drawer-toggle" id="drawer-toggle" aria-label="Open menu" aria-expanded="false">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
                <div class="admin-header__copy">
                    <p class="eyebrow">@yield('eyebrow', 'Admin')</p>
                    <h2>@yield('heading', 'Overview')</h2>
                    @hasSection('subheading')
                        <p class="admin-header__lede">@yield('subheading')</p>
                    @endif
                </div>

                <div class="admin-header__actions">
                    <span class="admin-chip">Signed in</span>
                    <span class="meta">{{ auth()->user()?->email }}</span>
                    @yield('header_actions')
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

    <script>
    (function () {
        var drawerToggle = document.getElementById('drawer-toggle');
        var sidebar = document.getElementById('admin-sidebar');
        var backdrop = document.getElementById('drawer-backdrop');
        var mq = window.matchMedia('(max-width: 1080px)');

        function openDrawer() {
            sidebar.classList.add('is-open');
            backdrop.classList.add('is-visible');
            drawerToggle.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }

        function closeDrawer() {
            sidebar.classList.remove('is-open');
            backdrop.classList.remove('is-visible');
            drawerToggle.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        drawerToggle.addEventListener('click', function () {
            sidebar.classList.contains('is-open') ? closeDrawer() : openDrawer();
        });

        backdrop.addEventListener('click', closeDrawer);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
                closeDrawer();
            }
        });

        sidebar.addEventListener('click', function (e) {
            if (mq.matches && e.target.closest('.admin-nav a')) {
                closeDrawer();
            }
        });

        mq.addEventListener('change', function (e) {
            if (!e.matches) {
                closeDrawer();
            }
        });
    })();
    </script>

    <script>
    (function () {
        var toggle = document.getElementById('push-toggle');
        var label = document.getElementById('push-label');

        if (!toggle || !('serviceWorker' in navigator) || !('PushManager' in window)) {
            if (label) label.textContent = 'Push not supported';
            return;
        }

        var vapidKey = document.querySelector('meta[name="vapid-public-key"]');
        var csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (!vapidKey || !vapidKey.content || !csrfToken) {
            label.textContent = 'Push not configured';
            return;
        }

        function urlBase64ToUint8Array(base64String) {
            var padding = '='.repeat((4 - base64String.length % 4) % 4);
            var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            var rawData = atob(base64);
            var outputArray = new Uint8Array(rawData.length);
            for (var i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }

        function updateUI(subscribed) {
            label.textContent = subscribed ? 'Notifications on' : 'Enable notifications';
            toggle.classList.toggle('is-subscribed', subscribed);
            toggle.disabled = false;
        }

        navigator.serviceWorker.register('/sw.js').then(function (reg) {
            reg.pushManager.getSubscription().then(function (sub) {
                updateUI(!!sub);
            });
        });

        toggle.addEventListener('click', function () {
            toggle.disabled = true;
            navigator.serviceWorker.ready.then(function (reg) {
                reg.pushManager.getSubscription().then(function (sub) {
                    if (sub) {
                        fetch('/admin/push/unsubscribe', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken.content,
                            },
                            body: JSON.stringify({ endpoint: sub.endpoint }),
                        }).then(function () {
                            return sub.unsubscribe();
                        }).then(function () {
                            updateUI(false);
                        });
                    } else {
                        reg.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: urlBase64ToUint8Array(vapidKey.content),
                        }).then(function (newSub) {
                            var key = newSub.getKey('p256dh');
                            var auth = newSub.getKey('auth');
                            return fetch('/admin/push/subscribe', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken.content,
                                },
                                body: JSON.stringify({
                                    endpoint: newSub.endpoint,
                                    keys: {
                                        p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(key))),
                                        auth: btoa(String.fromCharCode.apply(null, new Uint8Array(auth))),
                                    },
                                }),
                            });
                        }).then(function () {
                            updateUI(true);
                        });
                    }
                });
            });
        });
    })();
    </script>
</body>
</html>
