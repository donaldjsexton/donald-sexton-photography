@extends('layouts.admin')

@section('title', 'Platform Settings')
@section('eyebrow', 'Platform')
@section('heading', 'Settings')
@section('subheading', 'Analytics, Google access, and import tools.')
@section('content')
    @php
        $currentTab = request('tab', 'analytics');
        $analyticsValue = old('google_analytics_measurement_id', $siteSettings->google_analytics_measurement_id ?: $resolvedAnalyticsMeasurementId);
    @endphp

    <div class="admin-settings-page">
        @if ($errors->any())
            <ul class="errors">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        @if (session('status_error'))
            <div class="admin-flash admin-flash--error">{{ session('status_error') }}</div>
        @endif

        <nav class="admin-section-nav admin-settings-nav" aria-label="Settings sections">
            <a class="{{ $currentTab === 'analytics' ? 'is-active' : '' }}" href="{{ route('admin.settings.edit', ['tab' => 'analytics']) }}#analytics-settings">Analytics</a>
            <a class="{{ $currentTab === 'integrations' ? 'is-active' : '' }}" href="{{ route('admin.settings.edit', ['tab' => 'integrations']) }}#integrations-settings">Google</a>
            <a class="{{ $currentTab === 'imports' ? 'is-active' : '' }}" href="{{ route('admin.settings.edit', ['tab' => 'imports']) }}#import-settings">Imports</a>
        </nav>

        <section class="admin-grid admin-grid--two admin-settings-grid" id="analytics-settings">
            <article class="admin-card admin-card--feature">
                <p class="eyebrow">Analytics</p>
                <h3 class="feature-title">Google Analytics</h3>
                <p class="section-copy">Add the GA4 ID used on the live site.</p>

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

                    <p class="meta">Use the format <code>G-XXXXXXXXXX</code>. Leave this blank if the site should use the server value instead.</p>

                    <button class="cta" type="submit" style="border: 0; cursor: pointer;">Save Analytics</button>
                </form>
            </article>

            <article class="admin-card">
                <p class="eyebrow">Current Setup</p>
                <div class="admin-list">
                    <div class="admin-list__item">
                        <strong>Saved ID</strong>
                        <span class="meta">{{ $resolvedAnalyticsMeasurementId ?: 'Not configured' }}</span>
                    </div>
                    <div class="admin-list__item">
                        <strong>Tracking</strong>
                        <span class="meta">{{ $resolvedAnalyticsMeasurementId ? 'Tracking is live on public pages.' : 'Tracking stays off until an ID is added.' }}</span>
                    </div>
                    <div class="admin-list__item">
                        <strong>After you save</strong>
                        <span class="meta">Check GA4 after the next deploy to make sure visits are coming through.</span>
                    </div>
                </div>
            </article>
        </section>

        <section class="admin-grid admin-grid--two admin-settings-grid" id="integrations-settings">
            <article class="admin-card admin-card--feature">
                <p class="eyebrow">Google</p>
                <h3 class="feature-title">{{ $googleConnected ? 'Google is connected' : 'Connect Google' }}</h3>
                <p class="section-copy">
                    Use one Google login for email, Search Console, reviews, and calendar.
                </p>

                @if ($googleConnected)
                    <div class="admin-list" style="margin-bottom: 1.5rem;">
                        <div class="admin-list__item">
                            <strong>Signed in as</strong>
                            <span class="meta">{{ $siteSettings->google_connected_email }}</span>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.settings.google.disconnect') }}">
                        @csrf
                        <button class="cta-secondary" type="submit" style="cursor: pointer; border: 0;">Disconnect Google</button>
                    </form>
                @else
                    <a class="cta" href="{{ route('admin.settings.google.connect') }}">Connect Google Account</a>
                @endif
            </article>

            <article class="admin-card">
                <p class="eyebrow">Services</p>
                <div class="admin-list">
                    @foreach ($googleScopes as $entry)
                        <div class="admin-list__item">
                            <strong>{{ $entry['label'] }}</strong>
                            @if ($googleConnected && $siteSettings->googleHasScope($entry['scope']))
                                <span class="meta" style="color: var(--color-success, #2e7d32);">Active</span>
                            @else
                                <span class="meta">{{ $googleConnected ? 'Not granted' : 'Not connected' }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($googleConnected)
                    <p class="meta" style="margin-top: 1rem;">If you need another Google service, disconnect and connect again so Google can ask for it.</p>
                @endif
            </article>
        </section>

        @if ($googleConnected && $siteSettings->googleHasScope('https://www.googleapis.com/auth/business.manage'))
            <section class="admin-grid admin-grid--two admin-settings-grid admin-settings-grid--single">
                <article class="admin-card">
                    <p class="eyebrow">Business Profile</p>
                    <h3 class="feature-title">Choose the listing to show</h3>
                    <p class="section-copy">
                        Pick the location shown in the dashboard review panel. Leave it blank to hide that panel.
                    </p>

                    @if (count($gbpListing) === 0)
                        <p class="meta" style="margin-bottom: 1rem;">
                            No listings came back for this login. If the Business Profile API is still waiting on approval, you can paste the location name below instead.
                        </p>

                        <form method="POST" action="{{ route('admin.settings.gbp.update') }}" class="admin-form">
                            @csrf
                            <label>
                                Location resource name
                                <input type="text" name="gbp_manual_location" placeholder="accounts/123/locations/456" value="{{ old('gbp_manual_location', $siteSettings->gbp_location_name) }}">
                            </label>
                            <p class="meta">You can copy this from the URL in <a href="https://business.google.com" target="_blank" rel="noopener">business.google.com</a>, or leave it blank to clear the current listing.</p>
                            <button class="cta" type="submit" style="border: 0; cursor: pointer;">Save Listing</button>
                        </form>
                    @else
                        @php
                            $currentSelection = $siteSettings->gbp_account_name && $siteSettings->gbp_location_name
                                ? $siteSettings->gbp_account_name.'|'.$siteSettings->gbp_location_name
                                : '';
                        @endphp

                        <form method="POST" action="{{ route('admin.settings.gbp.update') }}" class="admin-form">
                            @csrf

                            <label>
                                Listing
                                <select name="gbp_selection">
                                    <option value="">— None (hide reputation widget) —</option>
                                    @foreach ($gbpListing as $account)
                                        <optgroup label="{{ $account['account_label'] }}">
                                            @foreach ($account['locations'] as $location)
                                                @php $value = $account['account_name'].'|'.$location['name']; @endphp
                                                <option value="{{ $value }}" @selected($currentSelection === $value)>
                                                    {{ $location['title'] }}@if ($location['address']) — {{ $location['address'] }}@endif
                                                </option>
                                            @endforeach
                                            @if (count($account['locations']) === 0)
                                                <option value="" disabled>(no locations in this account)</option>
                                            @endif
                                        </optgroup>
                                    @endforeach
                                </select>
                            </label>

                            <button class="cta" type="submit" style="border: 0; cursor: pointer;">Save Listing</button>
                        </form>
                    @endif
                </article>
            </section>
        @endif

        <section class="admin-grid admin-grid--two admin-settings-grid" id="import-settings">
            <article class="admin-card admin-card--feature" id="wordpress-import">
                <p class="eyebrow">WordPress</p>
                <h3 class="feature-title">Import a WordPress export</h3>
                <p class="section-copy">Upload a WXR file to bring older posts into the site.</p>

                <form method="POST" action="{{ route('admin.imports.wordpress.store') }}" enctype="multipart/form-data" class="admin-form">
                    @csrf

                    <label>
                        WXR file
                        <input type="file" name="wxr_file" accept=".xml,text/xml,application/xml" required>
                    </label>

                    <p class="meta">For larger files, run the import from the server instead.</p>

                    <button class="cta" type="submit" style="border: 0; cursor: pointer;">Run WordPress Import</button>
                </form>
            </article>

            <article class="admin-card admin-card--feature" id="pictime-import">
                <p class="eyebrow">Pic-Time</p>
                <h3 class="feature-title">Import Pic-Time pages</h3>
                <p class="section-copy">Paste one URL per line to pull in copy and gallery images.</p>

                <form method="POST" action="{{ route('admin.imports.pictime.store') }}" class="admin-form">
                    @csrf

                    <label>
                        Pic-Time URLs
                        <textarea name="sources" rows="7" placeholder="https://gallery.example.com/blog/post-one&#10;https://gallery.example.com/blog/post-two" required>{{ old('sources') }}</textarea>
                    </label>

                    <label>
                        Destination
                        <select name="target">
                            <option value="auto" @selected(old('target', 'auto') === 'auto')>Auto detect</option>
                            <option value="weddings" @selected(old('target') === 'weddings')>Wedding stories</option>
                            <option value="journal" @selected(old('target') === 'journal')>Journal posts</option>
                        </select>
                    </label>

                    <p class="meta">For larger cleanup passes, use the command line.</p>

                    <button class="cta" type="submit" style="border: 0; cursor: pointer;">Run Pic-Time Import</button>
                </form>
            </article>
        </section>

        <section class="admin-toolbar admin-settings-toolbar">
            <div class="admin-settings-toolbar__copy">
                <p class="eyebrow">Recent Runs</p>
                <h3 class="feature-title">Latest import checks</h3>
                <p class="section-copy">A quick look at the most recent WordPress and Pic-Time jobs.</p>
            </div>

            <a class="cta-secondary" href="{{ route('admin.imports.index') }}">View Import History</a>
        </section>

        <section class="admin-grid admin-grid--two admin-settings-grid admin-settings-grid--activity">
            <article class="admin-card">
                <p class="eyebrow">WordPress Runs</p>
                <div class="admin-list">
                    @forelse ($wordpressImportRuns as $run)
                        <div class="admin-list__item">
                            <strong>{{ str($run->status)->headline() }}</strong>
                            <span class="meta">{{ $run->created_at?->format('M j, Y g:i A') ?: 'No timestamp recorded' }}</span>
                        </div>
                    @empty
                        <p class="meta">No WordPress import runs have been recorded yet.</p>
                    @endforelse
                </div>
            </article>

            <article class="admin-card">
                <p class="eyebrow">Pic-Time Runs</p>
                <div class="admin-list">
                    @forelse ($picTimeImportRuns as $run)
                        <div class="admin-list__item">
                            <strong>{{ str($run->status)->headline() }}</strong>
                            <span class="meta">{{ $run->created_at?->format('M j, Y g:i A') ?: 'No timestamp recorded' }}</span>
                        </div>
                    @empty
                        <p class="meta">No Pic-Time import runs have been recorded yet.</p>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
@endsection
