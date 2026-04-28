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

    <div class="admin-settings-page admin-settings-page--{{ $currentTab }}" data-tab="{{ $currentTab }}">
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
            <a class="{{ $currentTab === 'discovery' ? 'is-active' : '' }}" href="{{ route('admin.settings.edit', ['tab' => 'discovery']) }}#discovery-settings">Discovery</a>
            <a class="{{ $currentTab === 'imports' ? 'is-active' : '' }}" href="{{ route('admin.settings.edit', ['tab' => 'imports']) }}#import-settings">Imports</a>
        </nav>

        @if ($currentTab === 'analytics')
            <section class="admin-dashboard-row">
                <x-admin.section-header
                    eyebrow="Analytics"
                    title="Google Analytics"
                    description="Control the GA4 measurement ID embedded on every public page."
                />

                <div class="admin-stat-grid">
                    @foreach ($analyticsStats as $stat)
                        <x-admin.stat :label="$stat['label']" :value="$stat['value']" :meta="$stat['meta']" />
                    @endforeach
                </div>
            </section>

            <section class="admin-dashboard-row" id="analytics-settings">
                <article class="admin-card admin-card--feature admin-settings-focus">
                    <p class="eyebrow">Setup</p>
                    <h3 class="feature-title">Save your GA4 measurement ID</h3>
                    <p class="section-copy">Use the format <code>G-XXXXXXXXXX</code>. Leave this blank to fall back to the server value.</p>

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

                        <button class="cta" type="submit" style="border: 0; cursor: pointer;">Save Analytics</button>
                    </form>
                </article>
            </section>
        @endif

        @if ($currentTab === 'integrations')
            <section class="admin-dashboard-row">
                <x-admin.section-header
                    eyebrow="Google"
                    title="{{ $googleConnected ? 'Google is connected' : 'Connect Google' }}"
                    description="Use one Google login for email, Search Console, reviews, and calendar."
                />

                <div class="admin-stat-grid">
                    @foreach ($integrationsStats as $stat)
                        <x-admin.stat :label="$stat['label']" :value="$stat['value']" :meta="$stat['meta']" />
                    @endforeach
                </div>
            </section>

            <section class="admin-dashboard-row" id="integrations-settings">
                <article class="admin-card admin-card--feature admin-settings-focus">
                    @if ($googleConnected)
                        <p class="eyebrow">Services</p>
                        <h3 class="feature-title">What Google can do for this site</h3>
                        <p class="section-copy">Signed in as <strong>{{ $siteSettings->google_connected_email }}</strong>. If you need another service, disconnect and connect again so Google can ask for it.</p>

                        <div class="admin-services-grid">
                            @foreach ($googleScopes as $entry)
                                @php $scopeActive = $siteSettings->googleHasScope($entry['scope']); @endphp
                                <div class="admin-services-grid__item {{ $scopeActive ? 'is-active' : '' }}">
                                    <strong>{{ $entry['label'] }}</strong>
                                    <x-admin.badge :tone="$scopeActive ? 'completed' : 'archived'">
                                        {{ $scopeActive ? 'Active' : 'Not granted' }}
                                    </x-admin.badge>
                                </div>
                            @endforeach
                        </div>

                        <form method="POST" action="{{ route('admin.settings.google.disconnect') }}">
                            @csrf
                            <button class="cta-secondary" type="submit" style="cursor: pointer; border: 0;">Disconnect Google</button>
                        </form>
                    @else
                        <p class="eyebrow">Setup</p>
                        <h3 class="feature-title">Connect a Google account</h3>
                        <p class="section-copy">Connect Google so Gmail, Search Console, reviews, and calendar all work together without separate logins.</p>
                        <a class="cta" href="{{ route('admin.settings.google.connect') }}">Connect Google Account</a>
                    @endif
                </article>
            </section>

            @if ($googleConnected && $siteSettings->googleHasScope('https://www.googleapis.com/auth/business.manage'))
                <section class="admin-dashboard-row">
                    <x-admin.section-header
                        eyebrow="Business Profile"
                        title="Choose the listing to show"
                        description="Pick the location shown in the dashboard review panel. Leave it blank to hide that panel."
                    />

                    <article class="admin-card admin-settings-focus">
                        @if (count($gbpListing) === 0)
                            <p class="meta">No listings came back for this login. If the Business Profile API is still waiting on approval, you can paste the location name below instead.</p>

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
        @endif

        @if ($currentTab === 'discovery')
            <section class="admin-dashboard-row" id="discovery-settings">
                <x-admin.section-header
                    eyebrow="Discovery"
                    title="Search engines, AI, and social"
                    description="Verification codes, social profiles, and the IndexNow key. These power schema.org sameAs, Pinterest Rich Pins, and faster indexing."
                />

                <article class="admin-card admin-card--feature admin-settings-focus">
                    <form method="POST" action="{{ route('admin.settings.update') }}" class="admin-form">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="return_tab" value="discovery">

                        <p class="eyebrow">Social profiles</p>
                        <p class="section-copy">Linked profiles get added to <code>schema.org/sameAs</code>, which AI search engines and Google use to confirm this is the right business.</p>

                        @foreach ([
                            'instagram_url' => 'Instagram URL',
                            'pinterest_url' => 'Pinterest URL',
                            'facebook_url' => 'Facebook URL',
                            'youtube_url' => 'YouTube URL',
                            'tiktok_url' => 'TikTok URL',
                            'x_url' => 'X / Twitter URL',
                        ] as $field => $label)
                            <label>
                                {{ $label }}
                                <input type="url" name="{{ $field }}" value="{{ old($field, $siteSettings->{$field}) }}" placeholder="https://" spellcheck="false">
                            </label>
                        @endforeach

                        <p class="eyebrow">Site verification</p>
                        <p class="section-copy">Paste only the verification token (the value of the <code>content</code> attribute), not the full meta tag.</p>

                        <label>
                            Google Search Console
                            <input type="text" name="google_site_verification" value="{{ old('google_site_verification', $siteSettings->google_site_verification) }}" spellcheck="false">
                        </label>
                        <label>
                            Bing Webmaster Tools
                            <input type="text" name="bing_site_verification" value="{{ old('bing_site_verification', $siteSettings->bing_site_verification) }}" spellcheck="false">
                        </label>
                        <label>
                            Pinterest domain claim
                            <input type="text" name="pinterest_site_verification" value="{{ old('pinterest_site_verification', $siteSettings->pinterest_site_verification) }}" spellcheck="false">
                        </label>

                        <p class="eyebrow">IndexNow</p>
                        <p class="section-copy">Generate a hex string (8–128 chars). The site exposes it at <code>/{key}.txt</code> automatically and pings IndexNow whenever a journal post, wedding story, or page publishes.</p>

                        <label>
                            IndexNow key
                            <input type="text" name="indexnow_key" value="{{ old('indexnow_key', $siteSettings->indexnow_key) }}" placeholder="e.g. {{ bin2hex(random_bytes(16)) }}" spellcheck="false">
                        </label>

                        <button class="cta" type="submit" style="border: 0; cursor: pointer;">Save Discovery</button>
                    </form>
                </article>
            </section>
        @endif

        @if ($currentTab === 'imports')
            <section class="admin-dashboard-row">
                <x-admin.section-header
                    eyebrow="Imports"
                    title="Import tools"
                    description="Bring WordPress and Pic-Time posts into the site. Longer jobs should run from the command line."
                />

                <div class="admin-stat-grid">
                    @foreach ($importsStats as $stat)
                        <x-admin.stat :label="$stat['label']" :value="$stat['value']" :meta="$stat['meta']" />
                    @endforeach
                </div>
            </section>

            <section class="admin-dashboard-row admin-settings-grid admin-settings-grid--imports" id="import-settings">
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

            <section class="admin-dashboard-row">
                <x-admin.section-header
                    eyebrow="Recent Runs"
                    title="Latest import checks"
                    description="A quick look at the most recent WordPress and Pic-Time jobs."
                >
                    <a class="cta-secondary" href="{{ route('admin.imports.index') }}">View Full Activity</a>
                </x-admin.section-header>

                <div class="admin-import-run-list">
                    @forelse ($recentImportRuns as $run)
                        @php
                            $sourceLabel = match ($run->source_type) {
                                'wordpress' => 'WordPress',
                                'pictime' => 'Pic-Time',
                                default => ucfirst((string) $run->source_type),
                            };
                            $statusTone = match ($run->status) {
                                'completed' => 'completed',
                                'failed' => 'failed',
                                'running' => 'running',
                                default => 'archived',
                            };
                            $startedAt = $run->started_at?->format('M j, Y g:i A') ?: $run->created_at?->format('M j, Y g:i A') ?: 'No start time recorded';
                        @endphp

                        <article class="admin-import-run">
                            <div class="admin-import-run__header">
                                <div class="admin-import-run__copy">
                                    <p class="eyebrow">{{ $sourceLabel }} Import</p>
                                    <h3 class="admin-import-run__title">{{ $startedAt }}</h3>
                                </div>

                                <x-admin.badge :tone="$statusTone">{{ str($run->status)->headline() }}</x-admin.badge>
                            </div>
                        </article>
                    @empty
                        <p class="meta">No import runs have been recorded yet.</p>
                    @endforelse
                </div>
            </section>
        @endif
    </div>
@endsection
