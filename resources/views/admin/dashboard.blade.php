@extends('layouts.admin')

@section('title', 'Admin Dashboard')
@section('heading', 'Dashboard')
@section('content')
    <section class="admin-grid admin-grid--stats">
        @foreach ($stats as $stat)
            <article class="admin-card">
                <p class="eyebrow">{{ $stat['label'] }}</p>
                <p class="admin-stat">{{ $stat['value'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="admin-grid admin-grid--two">
        <article class="admin-card">
            <p class="eyebrow">Homepage</p>
            <h3 class="feature-title">{{ $homepageSetting?->hero_heading ?: 'No homepage settings saved yet.' }}</h3>
            <p class="meta">Curated stories, testimonials, and hero copy are managed here.</p>
            <a class="cta-secondary" href="{{ route('admin.homepage.edit') }}">Edit Homepage</a>
        </article>

        <article class="admin-card">
            <p class="eyebrow">Import Tools</p>
            <h3 class="feature-title">Bring legacy and temporary external content into the CMS.</h3>
            <p class="meta">Legacy XML and temporary Pic-Time imports both flow through tracked import runs so content can be re-ingested without ad hoc copying.</p>
            <div class="cta-row">
                <a class="cta-secondary" href="{{ route('admin.imports.wordpress.index') }}">Legacy XML</a>
                <a class="cta-secondary" href="{{ route('admin.imports.pictime.index') }}">Pic-Time</a>
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
