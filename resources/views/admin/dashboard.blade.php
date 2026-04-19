@extends('layouts.admin')

@section('title', 'Admin Dashboard')
@section('eyebrow', 'Admin')
@section('heading', 'Overview')
@section('subheading', 'Pipeline, marketing, and content operations in a single observability view.')
@section('content')
    <section class="admin-dashboard-row">
        <x-admin.section-header
            eyebrow="Business Pulse"
            title="Lead pipeline & conversion"
            description="How the inquiry funnel is moving this week, which stages are holding leads, and where the best sources are coming from."
        />

        <div class="admin-stat-grid">
            @foreach ($businessPulse['stats'] as $stat)
                <x-admin.stat
                    :label="$stat['label']"
                    :value="$stat['value']"
                    :meta="$stat['meta']"
                />
            @endforeach
        </div>

        <div class="admin-grid admin-grid--two">
            <x-admin.panel eyebrow="Inquiry funnel">
                <x-admin.list>
                    @foreach ($businessPulse['funnel'] as $stage)
                        <x-admin.list-item
                            :title="$stage['label'].' · '.$stage['value']"
                            :meta="$stage['context']"
                        />
                    @endforeach
                </x-admin.list>
            </x-admin.panel>

            <x-admin.panel eyebrow="Top sources (all time)">
                <x-admin.list>
                    @forelse ($businessPulse['topSources'] as $source)
                        <x-admin.list-item
                            :title="$source['label']"
                            :meta="$source['meta']"
                        />
                    @empty
                        <p class="meta">No source data captured yet. Tag inquiry forms with a source to populate this panel.</p>
                    @endforelse
                </x-admin.list>
            </x-admin.panel>
        </div>
    </section>

    <section class="admin-dashboard-row">
        <x-admin.section-header
            eyebrow="Quick Actions"
            title="Common next steps"
        />

        <div class="admin-action-list">
            @foreach ($quickActions as $action)
                <x-admin.action-card
                    :href="$action['href']"
                    :label="$action['label']"
                    :description="$action['description']"
                />
            @endforeach
        </div>
    </section>

    <section class="admin-dashboard-row" data-collapsible>
        <x-admin.section-header
            eyebrow="Marketing & SEO"
            title="Discovery, coverage, and attribution"
            description="Signals across analytics, SEO metadata, and UTM-tagged traffic. Stubs remain where a live service is not yet connected."
        />

        @php
            $activeSignals = collect($marketingHealth['signals'])->reject(fn ($s) => $s['tone'] === 'neutral');
        @endphp

        @if ($activeSignals->isNotEmpty())
            <div class="admin-stat-grid">
                @foreach ($activeSignals as $signal)
                    <x-admin.signal-card
                        :tone="$signal['tone']"
                        :label="$signal['label']"
                        :value="$signal['value']"
                        :description="$signal['description']"
                    />
                @endforeach
            </div>
        @endif

        <div class="admin-grid admin-grid--two">
            <x-admin.panel eyebrow="SEO metadata coverage">
                <x-admin.list>
                    @foreach ($marketingHealth['seoCoverage'] as $row)
                        <x-admin.list-item
                            :title="$row['label'].' · '.$row['value']"
                            :meta="$row['context']"
                        />
                    @endforeach
                </x-admin.list>
            </x-admin.panel>

            <x-admin.panel eyebrow="UTM attribution (last 90 days)">
                <x-admin.list>
                    @forelse ($marketingHealth['attribution'] as $row)
                        <x-admin.list-item
                            :title="$row['label']"
                            :meta="$row['meta']"
                        />
                    @empty
                        <p class="meta">No UTM-tagged inquiries in the last 90 days. Tag outbound links with utm_source to start attributing traffic.</p>
                    @endforelse
                </x-admin.list>
            </x-admin.panel>
        </div>
    </section>

    <section class="admin-dashboard-row" data-collapsible>
        <x-admin.section-header
            eyebrow="Content Operations"
            title="Publishing, drafts, and system status"
            description="What's live, what's queued, and how the CMS and import systems are behaving right now."
        />

        <div class="admin-stat-grid">
            @foreach ($contentOps['stats'] as $stat)
                <x-admin.stat
                    :label="$stat['label']"
                    :value="$stat['value']"
                    :meta="$stat['meta']"
                />
            @endforeach
        </div>

        <div class="admin-grid admin-grid--two">
            <x-admin.panel eyebrow="System status">
                <div class="admin-inline-grid">
                    @foreach ($contentOps['systemPanels'] as $panel)
                        <x-admin.signal-card
                            :tone="$panel['tone']"
                            :label="$panel['label']"
                            :value="$panel['value']"
                            :description="$panel['description']"
                        />
                    @endforeach
                </div>
            </x-admin.panel>

            <x-admin.panel eyebrow="Drafts & publishing radar">
                <x-admin.list>
                    @foreach ($contentOps['radar'] as $item)
                        <x-admin.list-item
                            :title="$item['label'].' · '.$item['value']"
                            :meta="$item['context']"
                        />
                    @endforeach
                </x-admin.list>
            </x-admin.panel>
        </div>

        <div class="admin-grid admin-grid--two">
            <x-admin.panel eyebrow="Recent inquiries">
                <x-admin.list>
                    @forelse ($recentInquiries as $inquiry)
                        <x-admin.list-item
                            :title="$inquiry->primary_name"
                            :meta="$inquiry->email.' · '.str($inquiry->status)->replace('_', ' ')->headline()"
                        />
                    @empty
                        <p class="meta">No inquiries have been recorded yet.</p>
                    @endforelse
                </x-admin.list>
                <a class="cta-secondary" href="{{ route('admin.inquiries.index') }}">Open Inquiries</a>
            </x-admin.panel>

            <x-admin.panel eyebrow="Recent imports">
                <x-admin.list>
                    @forelse ($recentImports as $import)
                        <x-admin.list-item
                            :title="ucfirst($import->source_type)"
                            :meta="$import->status.' · '.$import->created_at?->format('M j, Y g:i A')"
                        />
                    @empty
                        <p class="meta">No import runs have been recorded yet.</p>
                    @endforelse
                </x-admin.list>
                <a class="cta-secondary" href="{{ route('admin.imports.index') }}">Open Import Activity</a>
            </x-admin.panel>
        </div>

    </section>

    <script>
        (function () {
            const mq = window.matchMedia('(max-width: 840px)');
            const sections = document.querySelectorAll('[data-collapsible]');

            sections.forEach(function (section) {
                const header = section.querySelector('.admin-section-header');
                if (!header) return;

                // Add chevron indicator
                const chevron = document.createElement('span');
                chevron.className = 'collapsible-chevron';
                chevron.setAttribute('aria-hidden', 'true');
                header.appendChild(chevron);

                // Wrap non-header children in a container for collapsing
                const body = document.createElement('div');
                body.className = 'collapsible-body';
                const children = Array.from(section.children).filter(function (el) {
                    return el !== header;
                });
                children.forEach(function (child) {
                    body.appendChild(child);
                });
                section.appendChild(body);

                header.addEventListener('click', function () {
                    if (!mq.matches) return;
                    const isCollapsed = section.getAttribute('data-collapsed') === 'true';
                    section.setAttribute('data-collapsed', isCollapsed ? 'false' : 'true');
                });
            });

            function applyState() {
                sections.forEach(function (section) {
                    if (mq.matches) {
                        if (!section.hasAttribute('data-collapsed')) {
                            section.setAttribute('data-collapsed', 'true');
                        }
                    } else {
                        section.removeAttribute('data-collapsed');
                    }
                });
            }

            applyState();
            mq.addEventListener('change', applyState);
        })();
    </script>
@endsection
