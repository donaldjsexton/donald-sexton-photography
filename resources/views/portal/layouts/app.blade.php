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
        .portal-header { display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px; }
        .portal-header__top { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 12px; }
        .portal-header__brand p { margin: 0; font-size: 11px; letter-spacing: 0.2em; text-transform: uppercase; color: #7a6555; }
        .portal-header__brand h1 { margin: 4px 0 0; font-size: 18px; letter-spacing: 0.04em; }
        .portal-header__account { display: flex; align-items: center; gap: 14px; font-size: 14px; flex-shrink: 0; }
        .portal-header__account form { display: inline; }
        .portal-header__account button { background: none; border: 0; cursor: pointer; font: inherit; color: #6b5446; padding: 8px 4px; min-height: 44px; }
        .portal-header__nav { display: flex; gap: 18px; flex-wrap: wrap; align-items: center; font-size: 14px; }
        .portal-header__nav a { text-decoration: none; padding: 8px 4px; min-height: 44px; line-height: 28px; }
        .portal-header__nav a.is-active { font-weight: 600; border-bottom: 2px solid #2d1d15; }
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
        .field input, .field select, .field textarea { width: 100%; padding: 12px 14px; border: 1px solid #d9c8b8; border-radius: 8px; font-size: 16px; min-height: 44px; box-sizing: border-box; background: #fff; color: #2d1d15; font-family: inherit; }
        .field textarea { min-height: 96px; resize: vertical; }
        .field-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .field-grid > .field { margin-bottom: 0; }
        .field-grid--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .check-group { display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px; }
        .check { display: flex; align-items: flex-start; gap: 12px; font-size: 14px; cursor: pointer; }
        .check input { margin: 4px 0 0; width: 18px; height: 18px; flex: 0 0 18px; }
        .check .label { color: #2d1d15; }
        .check .help { display: block; color: #6b5446; font-size: 13px; margin-top: 2px; }
        .form-section { margin-top: 8px; padding-top: 24px; border-top: 1px solid #e7d8c5; }
        .form-section:first-of-type { margin-top: 0; padding-top: 0; border-top: 0; }
        @media (max-width: 600px) {
            .field-grid, .field-grid--3 { grid-template-columns: 1fr; }
        }
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
            .card table thead tr { position: absolute; clip: rect(0 0 0 0); width: 1px; height: 1px; overflow: hidden; white-space: nowrap; }
            .card table, .card table tbody, .card table tr, .card table td { display: block; width: 100%; }
            .card table tr { padding: 14px 0; border-bottom: 1px solid #e7d8c5; }
            .card table tbody tr:last-child { border-bottom: 0; padding-bottom: 0; }
            .card table tbody tr:first-child { padding-top: 4px; }
            .card table td { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 6px 0; border: 0; text-align: right; }
            .card table td::before { content: attr(data-label); font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #6b5446; font-weight: 600; text-align: left; flex: 0 0 auto; }
            .card table td.num { text-align: right; }
            .card table td:last-child:not([data-label]) { justify-content: flex-end; margin-top: 8px; }
            .card table td:last-child:not([data-label])::before { content: none; }
            .card table td:last-child:not([data-label]) a { display: inline-block; padding: 10px 18px; min-height: 44px; line-height: 24px; box-sizing: border-box; background: #2d1d15; color: #fff; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; }
            .card table tfoot tr { display: flex; justify-content: space-between; align-items: baseline; padding: 4px 0; border: 0; }
            .card table tfoot td { display: inline; padding: 0; border: 0; text-align: left; flex: 0 1 auto; margin: 0; }
            .card table tfoot td:last-child { text-align: right; margin-top: 0; }
            .card table tfoot td::before { content: none; }
        }
    </style>
</head>
<body>
    @yield('layout')

    @hasSection('content')
        <div class="portal-shell">
            <header class="portal-header">
                <div class="portal-header__top">
                    <div class="portal-header__brand">
                        <p>{{ config('payments.business.name') }}</p>
                        <h1>Client Portal</h1>
                    </div>
                    <div class="portal-header__account">
                        <span class="meta">{{ \App\Support\Portal::user()?->portalGreeting() }}</span>
                        <form method="POST" action="{{ route('portal.logout') }}">
                            @csrf
                            <button type="submit">Sign out</button>
                        </form>
                    </div>
                </div>
                <nav class="portal-header__nav">
                    <a href="{{ route('portal.dashboard') }}" class="{{ request()->routeIs('portal.dashboard') ? 'is-active' : '' }}">Overview</a>
                    <a href="{{ route('portal.invoices.index') }}" class="{{ request()->routeIs('portal.invoices.*') ? 'is-active' : '' }}">Invoices</a>
                    <a href="{{ route('portal.contracts.index') }}" class="{{ request()->routeIs('portal.contracts.*') ? 'is-active' : '' }}">Contracts</a>
                    @if (\App\Support\Portal::user() instanceof \App\Models\Client)
                        <a href="{{ route('portal.settings.edit') }}" class="{{ request()->routeIs('portal.settings.*') ? 'is-active' : '' }}">Settings</a>
                    @endif
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
