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
    @php
        $navigationGroups = [
            [
                'label' => 'Content',
                'items' => [
                    ['label' => 'Dashboard', 'href' => route('admin.dashboard'), 'patterns' => ['admin.dashboard']],
                    ['label' => 'Homepage', 'href' => route('admin.homepage.edit'), 'patterns' => ['admin.homepage.*']],
                    ['label' => 'Pages', 'href' => route('admin.pages.index'), 'patterns' => ['admin.pages.*']],
                    ['label' => 'Wedding Stories', 'href' => route('admin.wedding-stories.index'), 'patterns' => ['admin.wedding-stories.*']],
                    ['label' => 'Journal Posts', 'href' => route('admin.journal-posts.index'), 'patterns' => ['admin.journal-posts.*']],
                    ['label' => 'Media', 'href' => route('admin.media.index'), 'patterns' => ['admin.media.*']],
                ],
            ],
            [
                'label' => 'Leads',
                'items' => [
                    ['label' => 'Inquiries', 'href' => route('admin.inquiries.index'), 'patterns' => ['admin.inquiries.*']],
                ],
            ],
            [
                'label' => 'Operations',
                'items' => [
                    ['label' => 'Settings', 'href' => route('admin.settings.edit'), 'patterns' => ['admin.settings.*', 'admin.imports.*']],
                ],
            ],
        ];
    @endphp

    <div class="admin-shell">
        <aside class="admin-sidebar">
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
            </div>
        </aside>

        <div class="admin-main">
            <header class="admin-header">
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
</body>
</html>
