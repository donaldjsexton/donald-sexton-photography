@extends('layouts.admin-v2')

@section('title', 'Platform Settings')
@section('eyebrow', 'Platform')
@section('heading', 'Settings')
@section('subheading', 'Analytics, Google access, and import tools.')
@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
        $meta = 'text-sm text-gray-500 dark:text-gray-400';
        $eyebrow = 'text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-400';
    @endphp
    @php
        $currentTab = request('tab', 'analytics');
        $analyticsValue = old('google_analytics_measurement_id', $siteSettings->google_analytics_measurement_id ?: $resolvedAnalyticsMeasurementId);
    @endphp

    <div class="admin-settings-page admin-settings-page--{{ $currentTab }} max-w-4xl" data-tab="{{ $currentTab }}">
        @if ($errors->any())
            <ul class="mb-4 list-disc rounded-xl border border-rose-200 bg-rose-50 px-8 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        @if (session('status_error'))
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">{{ session('status_error') }}</div>
        @endif

        <nav class="admin-settings-nav mb-6 flex flex-wrap gap-2" aria-label="Settings sections">
            @php
                $pill = 'rounded-full px-3.5 py-1.5 text-sm font-medium transition-colors';
                $on = 'bg-brand-500 text-white';
                $off = 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700 dark:hover:bg-gray-700';
            @endphp
            <a class="{{ $pill }} {{ $currentTab === 'analytics' ? 'is-active '.$on : $off }}" href="{{ route('admin.settings.edit', ['tab' => 'analytics']) }}#analytics-settings">Analytics</a>
            <a class="{{ $pill }} {{ $currentTab === 'integrations' ? 'is-active '.$on : $off }}" href="{{ route('admin.settings.edit', ['tab' => 'integrations']) }}#integrations-settings">Google</a>
            <a class="{{ $pill }} {{ $currentTab === 'payments' ? 'is-active '.$on : $off }}" href="{{ route('admin.settings.edit', ['tab' => 'payments']) }}#payments-settings">Payments</a>
            <a class="{{ $pill }} {{ $currentTab === 'business' ? 'is-active '.$on : $off }}" href="{{ route('admin.settings.edit', ['tab' => 'business']) }}#business-settings">Business</a>
            <a class="{{ $pill }} {{ $currentTab === 'discovery' ? 'is-active '.$on : $off }}" href="{{ route('admin.settings.edit', ['tab' => 'discovery']) }}#discovery-settings">Discovery</a>
            <a class="{{ $pill }} {{ $currentTab === 'imports' ? 'is-active '.$on : $off }}" href="{{ route('admin.settings.edit', ['tab' => 'imports']) }}#import-settings">Imports</a>
        </nav>

        @if ($currentTab === 'analytics')
            <section class="mb-6 space-y-4">
                <div class="admin-section-header flex flex-wrap items-end justify-between gap-3">
<div>
<p class="{{ $eyebrow }}">Analytics</p>
<h2 class="text-lg font-semibold text-gray-900 dark:text-white">Google Analytics</h2>
<p class="{{ $meta }}">Control the GA4 measurement ID embedded on every public page.</p>
</div>
</div>

                <div class="admin-stat-grid [display:grid] grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($analyticsStats as $stat)
                        <x-ui.stat-card :label="$stat['label']" :value="$stat['value']" :hint="$stat['meta']" />
                    @endforeach
                </div>
            </section>

            <section class="mb-6 space-y-4" id="analytics-settings">
                <x-ui.card class="admin-settings-focus space-y-3">
                    <p class="{{ $eyebrow }}">Setup</p>
                    <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Save your GA4 measurement ID</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Use the format <code>G-XXXXXXXXXX</code>. Leave this blank to fall back to the server value.</p>

                    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-none! space-y-4">
                        @csrf
                        @method('PUT')

                        <x-ui.field label="GA4 Measurement ID">
                            <input class="{{ $input }}"
                                type="text"
                                name="google_analytics_measurement_id"
                                value="{{ $analyticsValue }}"
                                placeholder="G-XXXXXXXXXX"
                                spellcheck="false"
                            >
                        </x-ui.field>

                        <x-ui.button type="submit">Save Analytics</x-ui.button>
                    </form>
                </x-ui.card>
            </section>
        @endif

        @if ($currentTab === 'integrations')
            <section class="mb-6 space-y-4">
                <div class="admin-section-header flex flex-wrap items-end justify-between gap-3">
<div>
<p class="{{ $eyebrow }}">Google</p>
<h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $googleConnected ? 'Google is connected' : 'Connect Google' }}</h2>
<p class="{{ $meta }}">Use one Google login for email, Search Console, reviews, and calendar.</p>
</div>
</div>

                <div class="admin-stat-grid [display:grid] grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($integrationsStats as $stat)
                        <x-ui.stat-card :label="$stat['label']" :value="$stat['value']" :hint="$stat['meta']" />
                    @endforeach
                </div>
            </section>

            <section class="mb-6 space-y-4" id="integrations-settings">
                <x-ui.card class="admin-settings-focus space-y-3">
                    @if ($googleConnected)
                        <p class="{{ $eyebrow }}">Services</p>
                        <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">What Google can do for this site</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Signed in as <strong>{{ $siteSettings->google_connected_email }}</strong>. Check Availability reads free/busy time on this login's primary calendar, the same calendar booking sync uses. If you need another service, disconnect and connect again so Google can ask for it.</p>

                        <div class="admin-services-grid [display:grid] grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach ($googleScopes as $entry)
                                @php $scopeActive = $siteSettings->googleHasScope($entry['scope']); @endphp
                                <div class="admin-services-grid__item flex items-center justify-between gap-3 rounded-xl border border-gray-200 px-4 py-3 text-sm dark:border-gray-700 {{ $scopeActive ? 'is-active' : '' }}">
                                    <strong>{{ $entry['label'] }}</strong>
                                    <x-ui.badge :tone="$scopeActive ? 'green' : 'gray'">
                                        {{ $scopeActive ? 'Active' : 'Not granted' }}
                                    </x-ui.badge>
                                </div>
                            @endforeach
                        </div>

                        <form method="POST" action="{{ route('admin.settings.google.disconnect') }}">
                            @csrf
                            <x-ui.button variant="secondary" type="submit">Disconnect Google</x-ui.button>
                        </form>
                    @else
                        <p class="{{ $eyebrow }}">Setup</p>
                        <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Connect a Google account</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Connect Google so Gmail, Search Console, reviews, and calendar all work together without separate logins.</p>
                        <x-ui.button :href="route('admin.settings.google.connect')">Connect Google Account</x-ui.button>
                    @endif
                </x-ui.card>
            </section>

            @if ($googleConnected && $siteSettings->googleHasScope('https://www.googleapis.com/auth/business.manage'))
                <section class="mb-6 space-y-4">
                    <div class="admin-section-header flex flex-wrap items-end justify-between gap-3">
<div>
<p class="{{ $eyebrow }}">Business Profile</p>
<h2 class="text-lg font-semibold text-gray-900 dark:text-white">Choose the listing to show</h2>
<p class="{{ $meta }}">Pick the location shown in the dashboard review panel. Leave it blank to hide that panel.</p>
</div>
</div>

                    <x-ui.card class="admin-settings-focus space-y-3">
                        @if (count($gbpListing) === 0)
                            <p class="{{ $meta }}">No listings came back for this login. If the Business Profile API is still waiting on approval, you can paste the location name below instead.</p>

                            <form method="POST" action="{{ route('admin.settings.gbp.update') }}" class="max-w-none! space-y-4">
                                @csrf
                                <x-ui.field label="Location resource name">
                                    <input class="{{ $input }}" type="text" name="gbp_manual_location" placeholder="accounts/123/locations/456" value="{{ old('gbp_manual_location', $siteSettings->gbp_location_name) }}">
                                </x-ui.field>
                                <p class="{{ $meta }}">You can copy this from the URL in <a href="https://business.google.com" target="_blank" rel="noopener">business.google.com</a>, or leave it blank to clear the current listing.</p>
                                <x-ui.button type="submit">Save Listing</x-ui.button>
                            </form>
                        @else
                            @php
                                $currentSelection = $siteSettings->gbp_account_name && $siteSettings->gbp_location_name
                                    ? $siteSettings->gbp_account_name.'|'.$siteSettings->gbp_location_name
                                    : '';
                            @endphp

                            <form method="POST" action="{{ route('admin.settings.gbp.update') }}" class="max-w-none! space-y-4">
                                @csrf

                                <x-ui.field label="Listing">
                                    <select class="{{ $input }}" name="gbp_selection">
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
                                </x-ui.field>

                                <x-ui.button type="submit">Save Listing</x-ui.button>
                            </form>
                        @endif
                    </x-ui.card>
                </section>
            @endif
        @endif

        @if ($currentTab === 'payments')
            <section class="mb-6 space-y-4">
                <div class="admin-section-header flex flex-wrap items-end justify-between gap-3">
<div>
<p class="{{ $eyebrow }}">Payments</p>
<h2 class="text-lg font-semibold text-gray-900 dark:text-white">Connect how you get paid</h2>
<p class="{{ $meta }}">Link your own payment accounts so invoice payments land directly with you.</p>
</div>
</div>
            </section>

            <section class="mb-6 space-y-4" id="payments-settings">
                <x-ui.card class="admin-settings-focus space-y-3">
                    @if ($siteSettings->squareIsConnected())
                        <p class="{{ $eyebrow }}">Square</p>
                        <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Square is connected</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Card payments on your invoices go straight to your Square account{{ $siteSettings->square_location_id ? ' (location '.$siteSettings->square_location_id.')' : '' }}.</p>
                        <form method="POST" action="{{ route('admin.settings.square.disconnect') }}">
                            @csrf
                            <x-ui.button variant="secondary" type="submit">Disconnect Square</x-ui.button>
                        </form>
                    @else
                        <p class="{{ $eyebrow }}">Square</p>
                        <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Connect Square</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Connect your Square account so clients can pay invoices by card and the money lands in your account.</p>
                        <x-ui.button :href="route('admin.settings.square.connect')">Connect Square Account</x-ui.button>
                    @endif
                </x-ui.card>

                <x-ui.card class="admin-settings-focus space-y-3">
                    <p class="{{ $eyebrow }}">PayPal</p>
                    <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">PayPal is coming soon</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Per-account PayPal connection is on the way. In the meantime, Square covers card payments.</p>
                </x-ui.card>
            </section>
        @endif

        @if ($currentTab === 'business')
            <section class="mb-6 space-y-4" id="business-settings">
                <div class="admin-section-header flex flex-wrap items-end justify-between gap-3">
<div>
<p class="{{ $eyebrow }}">Business</p>
<h2 class="text-lg font-semibold text-gray-900 dark:text-white">Contact, address, and price range</h2>
<p class="{{ $meta }}">These fields populate LocalBusiness schema so Google can show your phone, address, and price range in search and the local pack.</p>
</div>
</div>

                <x-ui.card class="admin-settings-focus space-y-3">
                    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-none! space-y-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="return_tab" value="business">

                        <p class="{{ $eyebrow }}">Contact</p>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-ui.field label="Phone (E.164 preferred)">
                                <input class="{{ $input }}" type="tel" name="business_phone" value="{{ old('business_phone', $siteSettings->business_phone) }}" placeholder="+17275551234" spellcheck="false">
                            </x-ui.field>
                            <x-ui.field label="Public email">
                                <input class="{{ $input }}" type="email" name="business_email" value="{{ old('business_email', $siteSettings->business_email) }}" placeholder="hello@donaldsextonphotography.com" spellcheck="false">
                            </x-ui.field>
                        </div>

                        <p class="{{ $eyebrow }}">Address</p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Even though you do not host clients at a storefront, a real mailing address strengthens local rankings.</p>

                        <x-ui.field label="Street address">
                            <input class="{{ $input }}" type="text" name="business_street_address" value="{{ old('business_street_address', $siteSettings->business_street_address) }}">
                        </x-ui.field>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-ui.field label="City">
                                <input class="{{ $input }}" type="text" name="business_locality" value="{{ old('business_locality', $siteSettings->business_locality) }}" placeholder="Clearwater">
                            </x-ui.field>
                            <x-ui.field label="State / region">
                                <input class="{{ $input }}" type="text" name="business_region" value="{{ old('business_region', $siteSettings->business_region) }}" placeholder="FL">
                            </x-ui.field>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-ui.field label="Postal code">
                                <input class="{{ $input }}" type="text" name="business_postal_code" value="{{ old('business_postal_code', $siteSettings->business_postal_code) }}" placeholder="33755">
                            </x-ui.field>
                            <x-ui.field label="Country (2-letter)">
                                <input class="{{ $input }}" type="text" name="business_country" maxlength="2" value="{{ old('business_country', $siteSettings->business_country) }}" placeholder="US">
                            </x-ui.field>
                        </div>

                        <p class="{{ $eyebrow }}">Geo</p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Latitude and longitude help schema.org surface the right service area. Find these on Google Maps by right-clicking your pin.</p>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-ui.field label="Latitude">
                                <input class="{{ $input }}" type="text" name="business_latitude" value="{{ old('business_latitude', $siteSettings->business_latitude) }}" placeholder="27.9659" inputmode="decimal" spellcheck="false">
                            </x-ui.field>
                            <x-ui.field label="Longitude">
                                <input class="{{ $input }}" type="text" name="business_longitude" value="{{ old('business_longitude', $siteSettings->business_longitude) }}" placeholder="-82.8001" inputmode="decimal" spellcheck="false">
                            </x-ui.field>
                        </div>

                        <p class="{{ $eyebrow }}">Positioning</p>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-ui.field label="Hours note">
                                <input class="{{ $input }}" type="text" name="business_hours_note" value="{{ old('business_hours_note', $siteSettings->business_hours_note) }}" placeholder="By appointment, Monday through Saturday">
                            </x-ui.field>
                            <x-ui.field label="Price range">
                                <input class="{{ $input }}" type="text" name="business_price_range" value="{{ old('business_price_range', $siteSettings->business_price_range) }}" maxlength="16" placeholder="$$$">
                            </x-ui.field>
                        </div>

                        <x-ui.button type="submit">Save Business</x-ui.button>
                    </form>
                </x-ui.card>
            </section>
        @endif

        @if ($currentTab === 'discovery')
            <section class="mb-6 space-y-4" id="discovery-settings">
                <div class="admin-section-header flex flex-wrap items-end justify-between gap-3">
<div>
<p class="{{ $eyebrow }}">Discovery</p>
<h2 class="text-lg font-semibold text-gray-900 dark:text-white">Search engines, AI, and social</h2>
<p class="{{ $meta }}">Verification codes, social profiles, and the IndexNow key. These power schema.org sameAs, Pinterest Rich Pins, and faster indexing.</p>
</div>
</div>

                <x-ui.card class="admin-settings-focus space-y-3">
                    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-none! space-y-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="return_tab" value="discovery">

                        <p class="{{ $eyebrow }}">Social profiles</p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Linked profiles get added to <code>schema.org/sameAs</code>, which AI search engines and Google use to confirm this is the right business.</p>

                        @foreach ([
                            'instagram_url' => 'Instagram URL',
                            'pinterest_url' => 'Pinterest URL',
                            'facebook_url' => 'Facebook URL',
                            'youtube_url' => 'YouTube URL',
                            'tiktok_url' => 'TikTok URL',
                            'x_url' => 'X / Twitter URL',
                        ] as $field => $label)
                            <x-ui.field label="{{ $label }}">
                                <input class="{{ $input }}" type="url" name="{{ $field }}" value="{{ old($field, $siteSettings->{$field}) }}" placeholder="https://" spellcheck="false">
                            </x-ui.field>
                        @endforeach

                        <p class="{{ $eyebrow }}">Site verification</p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Paste only the verification token (the value of the <code>content</code> attribute), not the full meta tag.</p>

                        <x-ui.field label="Google Search Console">
                            <input class="{{ $input }}" type="text" name="google_site_verification" value="{{ old('google_site_verification', $siteSettings->google_site_verification) }}" spellcheck="false">
                        </x-ui.field>
                        <x-ui.field label="Bing Webmaster Tools">
                            <input class="{{ $input }}" type="text" name="bing_site_verification" value="{{ old('bing_site_verification', $siteSettings->bing_site_verification) }}" spellcheck="false">
                        </x-ui.field>
                        <x-ui.field label="Pinterest domain claim">
                            <input class="{{ $input }}" type="text" name="pinterest_site_verification" value="{{ old('pinterest_site_verification', $siteSettings->pinterest_site_verification) }}" spellcheck="false">
                        </x-ui.field>

                        <p class="{{ $eyebrow }}">IndexNow</p>
                        <p class="text-sm text-gray-600 dark:text-gray-300">Generate a hex string (8–128 chars). The site exposes it at <code>/{key}.txt</code> automatically and pings IndexNow whenever a journal post, wedding story, or page publishes.</p>

                        <x-ui.field label="IndexNow key">
                            <input class="{{ $input }}" type="text" name="indexnow_key" value="{{ old('indexnow_key', $siteSettings->indexnow_key) }}" placeholder="e.g. {{ bin2hex(random_bytes(16)) }}" spellcheck="false">
                        </x-ui.field>

                        <x-ui.button type="submit">Save Discovery</x-ui.button>
                    </form>
                </x-ui.card>
            </section>
        @endif

        @if ($currentTab === 'imports')
            <section class="mb-6 space-y-4">
                <div class="admin-section-header flex flex-wrap items-end justify-between gap-3">
<div>
<p class="{{ $eyebrow }}">Imports</p>
<h2 class="text-lg font-semibold text-gray-900 dark:text-white">Import tools</h2>
<p class="{{ $meta }}">Bring WordPress and Pic-Time posts into the site. Longer jobs should run from the command line.</p>
</div>
</div>

                <div class="admin-stat-grid [display:grid] grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($importsStats as $stat)
                        <x-ui.stat-card :label="$stat['label']" :value="$stat['value']" :hint="$stat['meta']" />
                    @endforeach
                </div>
            </section>

            <section class="mb-6 admin-settings-grid admin-settings-grid--imports [display:grid] grid-cols-1 gap-4 lg:grid-cols-2" id="import-settings">
                <x-ui.card>
                    <p class="{{ $eyebrow }}">WordPress</p>
                    <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Import a WordPress export</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Upload a WXR file to bring older posts into the site.</p>

                    <form method="POST" action="{{ route('admin.imports.wordpress.store') }}" enctype="multipart/form-data" class="max-w-none! space-y-4">
                        @csrf

                        <x-ui.field label="WXR file">
                            <input class="{{ $input }}" type="file" name="wxr_file" accept=".xml,text/xml,application/xml" required>
                        </x-ui.field>

                        <p class="{{ $meta }}">For larger files, run the import from the server instead.</p>

                        <x-ui.button type="submit">Run WordPress Import</x-ui.button>
                    </form>
                </x-ui.card>

                <x-ui.card>
                    <p class="{{ $eyebrow }}">Pic-Time</p>
                    <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Import Pic-Time pages</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Paste one URL per line to pull in copy and gallery images.</p>

                    <form method="POST" action="{{ route('admin.imports.pictime.store') }}" class="max-w-none! space-y-4">
                        @csrf

                        <x-ui.field label="Pic-Time URLs">
                            <textarea class="{{ $input }}" name="sources" rows="7" placeholder="https://gallery.example.com/blog/post-one&#10;https://gallery.example.com/blog/post-two" required>{{ old('sources') }}</textarea>
                        </x-ui.field>

                        <x-ui.field label="Destination">
                            <select class="{{ $input }}" name="target">
                                <option value="auto" @selected(old('target', 'auto') === 'auto')>Auto detect</option>
                                <option value="weddings" @selected(old('target') === 'weddings')>Wedding stories</option>
                                <option value="journal" @selected(old('target') === 'journal')>Journal posts</option>
                            </select>
                        </x-ui.field>

                        <p class="{{ $meta }}">For larger cleanup passes, use the command line.</p>

                        <x-ui.button type="submit">Run Pic-Time Import</x-ui.button>
                    </form>
                </x-ui.card>
            </section>

            <section class="mb-6 space-y-4">
                <div class="admin-section-header flex flex-wrap items-end justify-between gap-3">
<div>
<p class="{{ $eyebrow }}">Recent Runs</p>
<h2 class="text-lg font-semibold text-gray-900 dark:text-white">Latest import checks</h2>
<p class="{{ $meta }}">A quick look at the most recent WordPress and Pic-Time jobs.</p>
</div>
<x-ui.button variant="secondary" :href="route('admin.imports.index')">View Full Activity</x-ui.button>
</div>

                <div class="admin-import-run-list space-y-3">
                    @forelse ($recentImportRuns as $run)
                        @php
                            $sourceLabel = match ($run->source_type) {
                                'wordpress' => 'WordPress',
                                'pictime' => 'Pic-Time',
                                default => ucfirst((string) $run->source_type),
                            };
                            $statusTone = match ($run->status) {
                                'completed' => 'green',
                                'failed' => 'red',
                                'running' => 'amber',
                                default => 'gray',
                            };
                            $startedAt = $run->started_at?->format('M j, Y g:i A') ?: $run->created_at?->format('M j, Y g:i A') ?: 'No start time recorded';
                        @endphp

                        <article class="admin-import-run rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                            <div class="admin-import-run__header flex items-start justify-between gap-3">
                                <div class="admin-import-run__copy">
                                    <p class="{{ $eyebrow }}">{{ $sourceLabel }} Import</p>
                                    <h3 class="admin-import-run__title text-sm font-medium text-gray-900 dark:text-white">{{ $startedAt }}</h3>
                                </div>

                                <x-ui.badge :tone="$statusTone">{{ str($run->status)->headline() }}</x-ui.badge>
                            </div>
                        </article>
                    @empty
                        <p class="{{ $meta }}">No import runs have been recorded yet.</p>
                    @endforelse
                </div>
            </section>
        @endif
    </div>
@endsection
