<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Client Portal') — {{ config('payments.business.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
    <style>
        :root { color-scheme: light; }
        body { margin: 0; font-family: 'Helvetica Neue', Arial, sans-serif; color: #2d1d15; background: #f6efe6; }
        a { color: #2d1d15; }
        .portal-shell { max-width: 880px; margin: 0 auto; padding: 24px 20px 64px; }
        .portal-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 24px; }
        .portal-header__brand p { margin: 0; font-size: 11px; letter-spacing: 0.2em; text-transform: uppercase; color: #7a6555; }
        .portal-header__brand h1 { margin: 4px 0 0; font-size: 18px; letter-spacing: 0.04em; }
        .portal-header__nav { display: flex; gap: 18px; flex-wrap: wrap; align-items: center; font-size: 14px; }
        .portal-header__nav a { text-decoration: none; padding: 8px 4px; min-height: 44px; line-height: 28px; }
        .portal-header__nav a.is-active { font-weight: 600; border-bottom: 2px solid #2d1d15; }
        .portal-header__nav form { display: inline; }
        .portal-header__nav button { background: none; border: 0; cursor: pointer; font: inherit; color: #6b5446; padding: 8px 4px; min-height: 44px; }
        .card { background: #fff; border: 1px solid #e7d8c5; border-radius: 12px; padding: 24px; }
        .stack > * + * { margin-top: 24px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .stat .label { font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase; color: #6b5446; }
        .stat .value { display: block; margin-top: 6px; font-size: 22px; font-weight: 600; }
        h2 { margin: 0 0 16px; font-size: 20px; font-weight: 500; }
        h3 { margin: 0 0 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #6b5446; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { text-align: left; padding: 10px 8px; border-bottom: 1px solid #e7d8c5; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #6b5446; }
        td { padding: 12px 8px; border-bottom: 1px solid #f6efe6; }
        td.num, th.num { text-align: right; }
        .pill { display: inline-block; padding: 3px 10px; border-radius: 999px; background: #f1e7da; color: #6b5446; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; }
        .btn { display: inline-block; padding: 11px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; min-height: 44px; line-height: 22px; box-sizing: border-box; }
        .btn-primary { background: #2d1d15; color: #fff; }
        .btn-secondary { background: transparent; color: #2d1d15; border: 1px solid #2d1d15; }
        .field { display: block; margin-bottom: 16px; font-size: 14px; }
        .field span { display: block; margin-bottom: 6px; color: #6b5446; }
        .field input { width: 100%; padding: 12px 14px; border: 1px solid #d9c8b8; border-radius: 8px; font-size: 16px; min-height: 44px; box-sizing: border-box; background: #fff; color: #2d1d15; }
        .errors { margin: 0 0 16px; padding: 12px 16px; background: #fbe9e0; border: 1px solid #e2a48c; border-radius: 8px; font-size: 14px; color: #6e2d18; }
        .flash { margin: 0 0 16px; padding: 12px 16px; background: #e8f1e2; border: 1px solid #aac49a; border-radius: 8px; font-size: 14px; color: #355a26; }
        .auth-shell { display: flex; justify-content: center; padding: 48px 16px; min-height: 100vh; box-sizing: border-box; }
        .auth-card { width: 100%; max-width: 420px; }
        .auth-card .brand { text-align: center; margin-bottom: 24px; }
        .auth-card .brand p { margin: 0; font-size: 11px; letter-spacing: 0.2em; text-transform: uppercase; color: #7a6555; }
        .auth-card .brand h1 { margin: 6px 0 0; font-size: 22px; }
        .meta { color: #6b5446; font-size: 13px; }
        @media (max-width: 600px) {
            .grid-3 { grid-template-columns: 1fr; }
            .card { padding: 18px; }
        }
    </style>
</head>
<body>
    @yield('layout')

    @hasSection('content')
        <div class="portal-shell">
            <header class="portal-header">
                <div class="portal-header__brand">
                    <p>{{ config('payments.business.name') }}</p>
                    <h1>Client Portal</h1>
                </div>
                <nav class="portal-header__nav">
                    <a href="{{ route('portal.dashboard') }}" class="{{ request()->routeIs('portal.dashboard') ? 'is-active' : '' }}">Overview</a>
                    <a href="{{ route('portal.invoices.index') }}" class="{{ request()->routeIs('portal.invoices.*') ? 'is-active' : '' }}">Invoices</a>
                    <span class="meta">{{ \App\Support\Portal::user()?->portalGreeting() }}</span>
                    <form method="POST" action="{{ route('portal.logout') }}">
                        @csrf
                        <button type="submit">Sign out</button>
                    </form>
                </nav>
            </header>

            @if (session('status'))
                <div class="flash">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <ul class="errors" style="list-style: none;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            @yield('content')
        </div>
    @endif
</body>
</html>
