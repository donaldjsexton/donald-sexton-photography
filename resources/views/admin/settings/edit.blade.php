@extends('layouts.admin')

@section('title', 'Platform Settings')
@section('eyebrow', 'Platform')
@section('heading', 'Settings')
@section('subheading', 'Analytics, imports, and system-level controls now live in one place.')
@section('content')
    @php
        $currentTab = request('tab', 'overview');
        $analyticsValue = old('google_analytics_measurement_id', $siteSettings->google_analytics_measurement_id ?: $resolvedAnalyticsMeasurementId);
    @endphp

    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <nav class="admin-section-nav" aria-label="Settings sections">
        <a class="{{ $currentTab === 'overview' ? 'is-active' : '' }}" href="{{ route('admin.settings.edit', ['tab' => 'overview']) }}#settings-overview">Overview</a>
        <a class="{{ $currentTab === 'analytics' ? 'is-active' : '' }}" href="{{ route('admin.settings.edit', ['tab' => 'analytics']) }}#analytics-settings">Analytics</a>
        <a class="{{ $currentTab === 'imports' ? 'is-active' : '' }}" href="{{ route('admin.settings.edit', ['tab' => 'imports']) }}#import-settings">Imports</a>
    </nav>

    <section class="admin-hero-panel" id="settings-overview">
        <div class="admin-hero-panel__copy">
            <p class="eyebrow">Platform Overview</p>
            <h3 class="feature-title">Treat the backend like infrastructure, not an afterthought.</h3>
            <p class="section-copy">This workspace centralizes measurement, migration tools, and operational visibility so the admin can evolve like the rest of the product.</p>
        </div>

        <div class="admin-inline-grid">
            <article class="admin-signal-card admin-signal-card--{{ $resolvedAnalyticsMeasurementId ? 'positive' : 'warning' }}">
                <p class="eyebrow">Analytics</p>
                <strong>{{ $resolvedAnalyticsMeasurementId ? 'Connected' : 'Pending' }}</strong>
                <span class="meta">{{ $resolvedAnalyticsMeasurementId ?: 'No GA4 measurement ID saved yet.' }}</span>
            </article>

            <article class="admin-signal-card admin-signal-card--neutral">
                <p class="eyebrow">WordPress Import</p>
                <strong>{{ $wordpressImportRuns->count() }}</strong>
                <span class="meta">Recent tracked runs available below.</span>
            </article>

            <article class="admin-signal-card admin-signal-card--neutral">
                <p class="eyebrow">Pic-Time Import</p>
                <strong>{{ $picTimeImportRuns->count() }}</strong>
                <span class="meta">Bridge external galleries into the CMS with audit history.</span>
            </article>
        </div>
    </section>

    <section class="admin-grid admin-grid--two" id="analytics-settings">
        <article class="admin-card admin-card--feature">
            <p class="eyebrow">Google Analytics</p>
            <h3 class="feature-title">Wire GA4 directly into the public site shell.</h3>
            <p class="section-copy">The tracking snippet only renders when a measurement ID is configured, so local and preview environments stay clean by default.</p>

            <form method="POST" action="{{ route('admin.settings.update') }}" class="admin-form">
                @csrf
                @method('PUT')

                <label>
                    GA4 Measurement ID
                    <input
                        type="text"
                        name="google_analytics_measurement_id"
                        value="{{ $analyticsValue }}"
                        placeholder="G-XXXXXXXXXX"
                        spellcheck="false"
                    >
                </label>

                <p class="meta">Use a current GA4 ID in the format <code>G-XXXXXXXXXX</code>. If this field is blank, the app will fall back to <code>GOOGLE_ANALYTICS_MEASUREMENT_ID</code> when present.</p>

                <button class="cta" type="submit" style="border: 0; cursor: pointer;">Save Platform Settings</button>
            </form>
        </article>

        <article class="admin-card">
            <p class="eyebrow">Runtime Status</p>
            <div class="admin-list">
                <div class="admin-list__item">
                    <strong>Resolved measurement ID</strong>
                    <span class="meta">{{ $resolvedAnalyticsMeasurementId ?: 'Not configured' }}</span>
                </div>
                <div class="admin-list__item">
                    <strong>Snippet behavior</strong>
                    <span class="meta">{{ $resolvedAnalyticsMeasurementId ? 'Public pages will emit the GA4 script.' : 'No analytics script is rendered on the frontend.' }}</span>
                </div>
                <div class="admin-list__item">
                    <strong>Recommended next step</strong>
                    <span class="meta">Verify traffic in GA4 realtime after deployment and confirm the production domain is added to your stream.</span>
                </div>
            </div>
        </article>
    </section>

    <section class="admin-grid admin-grid--two" id="import-settings">
        <article class="admin-card admin-card--feature" id="wordpress-import">
            <p class="eyebrow">Legacy Blog Import</p>
            <h3 class="feature-title">Ingest WordPress exports without losing structure.</h3>
            <p class="section-copy">This pass imports legacy journal entries, matches featured media, records redirects, and promotes real weddings into the story collection where appropriate.</p>

            <form method="POST" action="{{ route('admin.imports.wordpress.store') }}" enctype="multipart/form-data" class="admin-form">
                @csrf

                <label>
                    WXR file
                    <input type="file" name="wxr_file" accept=".xml,text/xml,application/xml" required>
                </label>

                <p class="meta">For large exports, use <code>php artisan wordpress:import /absolute/path/to/export.xml</code> from the server shell.</p>

                <button class="cta" type="submit" style="border: 0; cursor: pointer;">Run WordPress Import</button>
            </form>
        </article>

        <article class="admin-card admin-card--feature" id="pictime-import">
            <p class="eyebrow">Pic-Time Import</p>
            <h3 class="feature-title">Convert external gallery stories into first-party content.</h3>
            <p class="section-copy">Paste one Pic-Time URL per line to extract narrative copy, download gallery media, and classify the result into wedding stories or journal posts.</p>

            <form method="POST" action="{{ route('admin.imports.pictime.store') }}" class="admin-form">
                @csrf

                <label>
                    Pic-Time URLs
                    <textarea name="sources" rows="7" placeholder="https://gallery.example.com/blog/post-one&#10;https://gallery.example.com/blog/post-two" required>{{ old('sources') }}</textarea>
                </label>

                <label>
                    Target
                    <select name="target">
                        <option value="auto" @selected(old('target', 'auto') === 'auto')>Auto detect</option>
                        <option value="weddings" @selected(old('target') === 'weddings')>Wedding stories</option>
                        <option value="journal" @selected(old('target') === 'journal')>Journal posts</option>
                    </select>
                </label>

                <p class="meta">CLI support is available for XML exports, saved HTML pages, and larger repair passes using <code>php artisan pictime:import ... --target=auto</code>.</p>

                <button class="cta" type="submit" style="border: 0; cursor: pointer;">Run Pic-Time Import</button>
            </form>
        </article>
    </section>

    <section class="admin-grid admin-grid--two">
        <article class="admin-card">
            <p class="eyebrow">WordPress Activity</p>
            <div class="admin-list">
                @forelse ($wordpressImportRuns as $run)
                    <div class="admin-list__item">
                        <strong>{{ ucfirst($run->status) }}</strong>
                        <span class="meta">{{ $run->created_at?->format('M j, Y g:i A') }} · {{ $run->summary_json ? collect($run->summary_json)->map(fn ($value, $key) => str_replace('_', ' ', $key).': '.$value)->join(' · ') : 'No summary saved' }}</span>
                    </div>
                @empty
                    <p class="meta">No WordPress import runs have been recorded yet.</p>
                @endforelse
            </div>
        </article>

        <article class="admin-card">
            <p class="eyebrow">Pic-Time Activity</p>
            <div class="admin-list">
                @forelse ($picTimeImportRuns as $run)
                    <div class="admin-list__item">
                        <strong>{{ ucfirst($run->status) }}</strong>
                        <span class="meta">{{ $run->created_at?->format('M j, Y g:i A') }} · {{ $run->summary_json ? collect($run->summary_json)->map(fn ($value, $key) => str_replace('_', ' ', $key).': '.$value)->join(' · ') : 'No summary saved' }}</span>
                    </div>
                @empty
                    <p class="meta">No Pic-Time import runs have been recorded yet.</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="admin-card">
        <p class="eyebrow">Recent Import Activity</p>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Started</th>
                        <th>Summary</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentImportRuns as $run)
                        <tr>
                            <td>{{ ucfirst($run->source_type) }}</td>
                            <td>{{ $run->status }}</td>
                            <td>{{ $run->started_at?->format('M j, Y g:i A') ?: $run->created_at?->format('M j, Y g:i A') }}</td>
                            <td>
                                @if ($run->summary_json)
                                    <div class="admin-summary">
                                        @foreach ($run->summary_json as $key => $value)
                                            <span>{{ str_replace('_', ' ', $key) }}: {{ $value }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="meta">No summary saved.</span>
                                @endif
                            </td>
                            <td>{{ $run->error_log ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No import runs recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
