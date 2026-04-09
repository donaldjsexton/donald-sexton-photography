@extends('layouts.admin')

@section('title', 'Admin Dashboard')
@section('eyebrow', 'Admin')
@section('heading', 'Overview')
@section('subheading', 'Review content, leads, imports, and recent activity.')
@section('content')
    <section class="admin-dashboard-top">
        <article class="admin-card admin-dashboard-overview">
            <p class="eyebrow">At a glance</p>
            <h3 class="feature-title">Content and client work in one place.</h3>
            <p class="section-copy">Use this page to move between publishing work, lead management, and operational tasks without treating inquiries like an afterthought.</p>

            <div class="admin-dashboard-feature-grid">
                <article class="admin-dashboard-feature-card">
                    <p class="eyebrow">Homepage</p>
                    <h4>{{ $homepageSetting?->hero_heading ?: 'Homepage is still using fallback copy.' }}</h4>
                    <p class="meta">Curated stories, testimonials, and hero messaging are controlled from the homepage editor.</p>
                    <a class="cta-secondary" href="{{ route('admin.homepage.edit') }}">Edit Homepage</a>
                </article>

                <article class="admin-dashboard-feature-card">
                    <p class="eyebrow">Imports</p>
                    @if ($latestFailedImport)
                        <h4>Latest failure: {{ ucfirst($latestFailedImport->source_type) }}</h4>
                        <p class="meta">{{ $latestFailedImport->created_at?->format('M j, Y g:i A') }} · {{ $latestFailedImport->error_log ?: 'The run failed without a saved error message.' }}</p>
                    @else
                        <h4>No current failures.</h4>
                        <p class="meta">Tracked import runs are clean right now. Use Settings to launch a WordPress or Pic-Time ingestion pass.</p>
                    @endif
                    <a class="cta-secondary" href="{{ route('admin.settings.edit', ['tab' => 'imports']) }}#import-settings">Open Import Tools</a>
                </article>
            </div>
        </article>

        <div class="admin-dashboard-status-grid">
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
            <p class="eyebrow">Drafts and publishing</p>
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
            <p class="eyebrow">Recent Inquiries</p>
            <div class="admin-list">
                @forelse ($recentInquiries as $inquiry)
                    <div class="admin-list__item">
                        <strong>{{ $inquiry->primary_name }}</strong>
                        <span class="meta">{{ $inquiry->email }} · {{ str($inquiry->status)->replace('_', ' ')->headline() }}</span>
                    </div>
                @empty
                    <p class="meta">No inquiries have been recorded yet.</p>
                @endforelse
            </div>
            <a class="cta-secondary" href="{{ route('admin.inquiries.index') }}">Open Inquiries</a>
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
