@extends('layouts.admin')

@section('title', 'Admin Dashboard')
@section('eyebrow', 'Operations')
@section('heading', 'Dashboard')
@section('subheading', 'A system view for content, imports, and growth signals across the studio app.')
@section('content')
    <section class="admin-hero-panel">
        <div class="admin-hero-panel__copy">
            <p class="eyebrow">Control Room</p>
            <h3 class="feature-title">Operate this like a product team, not a plugin panel.</h3>
            <p class="section-copy">This dashboard is meant to feel closer to observability tooling: clear signals, fast actions, and a strong sense of what needs attention next.</p>
        </div>

        <div class="admin-inline-grid">
            @foreach ($systemPanels as $panel)
                <article class="admin-signal-card admin-signal-card--{{ $panel['tone'] }}">
                    <p class="eyebrow">{{ $panel['label'] }}</p>
                    <strong>{{ $panel['value'] }}</strong>
                    <span class="meta">{{ $panel['description'] }}</span>
                </article>
            @endforeach
        </div>
    </section>

    <section class="admin-stat-grid">
        @foreach ($primaryStats as $stat)
            <article class="admin-card admin-card--metric">
                <p class="eyebrow">{{ $stat['label'] }}</p>
                <p class="admin-stat">{{ $stat['value'] }}</p>
                <p class="meta">{{ $stat['meta'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="admin-grid admin-grid--two">
        <article class="admin-card">
            <p class="eyebrow">Content Radar</p>
            <div class="admin-list">
                @foreach ($contentRadar as $item)
                    <div class="admin-list__item">
                        <strong>{{ $item['label'] }} · {{ $item['value'] }}</strong>
                        <span class="meta">{{ $item['context'] }}</span>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="admin-card">
            <p class="eyebrow">Quick Actions</p>
            <div class="admin-action-list">
                @foreach ($quickActions as $action)
                    <a class="admin-action-card" href="{{ $action['href'] }}">
                        <strong>{{ $action['label'] }}</strong>
                        <span class="meta">{{ $action['description'] }}</span>
                    </a>
                @endforeach
            </div>
        </article>
    </section>

    <section class="admin-grid admin-grid--two">
        <article class="admin-card">
            <p class="eyebrow">Homepage Status</p>
            <h3 class="feature-title">{{ $homepageSetting?->hero_heading ?: 'Homepage is still using fallback copy.' }}</h3>
            <p class="meta">Curated stories, testimonials, and hero messaging are controlled from the homepage editor.</p>
            <a class="cta-secondary" href="{{ route('admin.homepage.edit') }}">Open Homepage Editor</a>
        </article>

        <article class="admin-card">
            <p class="eyebrow">Import Health</p>
            @if ($latestFailedImport)
                <h3 class="feature-title">Latest failure: {{ ucfirst($latestFailedImport->source_type) }}</h3>
                <p class="meta">{{ $latestFailedImport->created_at?->format('M j, Y g:i A') }} · {{ $latestFailedImport->error_log ?: 'The run failed without a saved error message.' }}</p>
            @else
                <h3 class="feature-title">No current failures.</h3>
                <p class="meta">Tracked import runs are clean right now. Use Settings to launch a WordPress or Pic-Time ingestion pass.</p>
            @endif
            <a class="cta-secondary" href="{{ route('admin.settings.edit', ['tab' => 'imports']) }}#import-settings">Open Import Center</a>
        </article>
    </section>

    <section class="admin-grid admin-grid--two">
        <article class="admin-card">
            <p class="eyebrow">Recent Inquiries</p>
            <div class="admin-list">
                @forelse ($recentInquiries as $inquiry)
                    <div class="admin-list__item">
                        <strong>{{ $inquiry->primary_name }}</strong>
                        <span class="meta">{{ $inquiry->email }} · {{ $inquiry->status }}</span>
                    </div>
                @empty
                    <p class="meta">No inquiries have been recorded yet.</p>
                @endforelse
            </div>
        </article>

        <article class="admin-card">
            <p class="eyebrow">Recent Imports</p>
            <div class="admin-list">
                @forelse ($recentImports as $import)
                    <div class="admin-list__item">
                        <strong>{{ ucfirst($import->source_type) }}</strong>
                        <span class="meta">{{ $import->status }} · {{ $import->created_at?->format('M j, Y g:i A') }}</span>
                    </div>
                @empty
                    <p class="meta">No import runs have been recorded yet.</p>
                @endforelse
            </div>
        </article>
    </section>
@endsection
