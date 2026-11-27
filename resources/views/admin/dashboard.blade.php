@extends('layouts.admin-v2')

@section('title', 'Admin Dashboard')
@section('eyebrow', 'Admin')
@section('heading', 'Overview')
@section('subheading', 'Pipeline, marketing, and content operations in a single view.')
@section('content')
    @php
        $link = 'text-sm text-brand-600 hover:underline dark:text-brand-400';
        $tones = ['positive' => 'green', 'critical' => 'red', 'good' => 'green', 'success' => 'green', 'ok' => 'green', 'warn' => 'amber', 'warning' => 'amber', 'bad' => 'red', 'danger' => 'red', 'error' => 'red'];
        $rowClass = 'block border-b border-gray-100 px-5 py-3 text-sm last:border-0 dark:border-gray-800';
        $hover = 'hover:bg-gray-50 dark:hover:bg-gray-800/60';
    @endphp

    {{-- Business pulse --}}
    <h2 class="mb-1 text-lg font-semibold text-gray-900 dark:text-white">Lead pipeline &amp; conversion</h2>
    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">How the inquiry funnel is moving this week and where the best sources are coming from.</p>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($businessPulse['stats'] as $stat)
            <x-ui.stat-card :label="$stat['label']" :value="$stat['value']" :hint="$stat['meta']" />
        @endforeach
    </div>
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-ui.card title="Inquiry funnel" flush>
            @foreach ($businessPulse['funnel'] as $stage)
                <div class="{{ $rowClass }}">
                    <p class="font-medium text-gray-900 dark:text-white">{{ $stage['label'] }} · {{ $stage['value'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $stage['context'] }}</p>
                </div>
            @endforeach
        </x-ui.card>
        <x-ui.card title="Top sources (all time)" flush>
            @forelse ($businessPulse['topSources'] as $source)
                <div class="{{ $rowClass }}">
                    <p class="font-medium text-gray-900 dark:text-white">{{ $source['label'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $source['meta'] }}</p>
                </div>
            @empty
                <p class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">No source data captured yet. Tag inquiry forms with a source to populate this panel.</p>
            @endforelse
        </x-ui.card>
    </div>

    {{-- On your plate --}}
    <h2 class="mb-1 mt-10 text-lg font-semibold text-gray-900 dark:text-white">Needs reply &amp; this week</h2>
    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Leads waiting on a first response, plus shoots in the next seven days.</p>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-ui.card title="Needs reply" flush>
            @if ($needsReply['count'] === 0)
                <p class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">All current leads have a first reply on the books.</p>
            @else
                <div class="px-5 pt-4">
                    <p class="text-3xl font-semibold text-gray-900 dark:text-white">{{ $needsReply['count'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ \Illuminate\Support\Str::plural('lead', $needsReply['count']) }} waiting
                        @if ($needsReply['oldestHours'] !== null)
                            · oldest {{ $needsReply['oldestHours'] }}h
                        @endif
                    </p>
                </div>
                <div class="mt-3">
                    @foreach ($needsReply['items'] as $inquiry)
                        <a class="{{ $rowClass }} {{ $hover }}" href="{{ route('admin.inquiries.edit', $inquiry) }}">
                            <p class="font-medium text-gray-900 dark:text-white">{{ $inquiry->primary_name }} · {{ $inquiry->event_date?->format('M j, Y') ?: 'No date' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $inquiry->email }} · received {{ $inquiry->created_at?->isFuture() ? 'just now' : $inquiry->created_at?->diffForHumans() }}</p>
                        </a>
                    @endforeach
                </div>
                <div class="px-5 py-3"><a class="{{ $link }}" href="{{ route('admin.inquiries.index', ['status' => 'new']) }}">Open new inquiries →</a></div>
            @endif
        </x-ui.card>

        <x-ui.card title="This week" flush>
            @if ($thisWeek->isEmpty())
                <p class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">No confirmed shoots in the next seven days.</p>
            @else
                <div class="px-5 pt-4">
                    <p class="text-3xl font-semibold text-gray-900 dark:text-white">{{ $thisWeek->count() }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::plural('shoot', $thisWeek->count()) }} through {{ today()->copy()->addDays(6)->format('M j') }}</p>
                </div>
                <div class="mt-3">
                    @foreach ($thisWeek as $job)
                        <a class="{{ $rowClass }} {{ $hover }}" href="{{ route('admin.booked-jobs.show', $job) }}">
                            <p class="font-medium text-gray-900 dark:text-white">{{ $job->couple_names ?: $job->summary ?: 'Booked shoot' }} · {{ $job->event_date?->format('D, M j') ?: '' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ trim(collect([$job->event_time, $job->location])->filter()->implode(' · ')) ?: 'Time/location TBD' }}</p>
                        </a>
                    @endforeach
                </div>
                <div class="px-5 py-3"><a class="{{ $link }}" href="{{ route('admin.booked-jobs.index') }}">Open calendar →</a></div>
            @endif
        </x-ui.card>
    </div>

    {{-- Quick actions --}}
    <h2 class="mb-4 mt-10 text-lg font-semibold text-gray-900 dark:text-white">Common next steps</h2>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($quickActions as $action)
            <a href="{{ $action['href'] }}" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-colors hover:border-brand-500 dark:border-gray-800 dark:bg-gray-800/50 dark:hover:border-brand-500">
                <p class="font-medium text-gray-900 dark:text-white">{{ $action['label'] }}</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $action['description'] }}</p>
            </a>
        @endforeach
    </div>

    {{-- Marketing --}}
    <h2 class="mb-1 mt-10 text-lg font-semibold text-gray-900 dark:text-white">Discovery, coverage, and attribution</h2>
    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Signals across analytics, SEO metadata, and UTM-tagged traffic.</p>
    @php
        $activeSignals = collect($marketingHealth['signals'])->reject(fn ($s) => $s['tone'] === 'neutral');
    @endphp
    @if ($activeSignals->isNotEmpty())
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($activeSignals as $signal)
                <x-ui.card>
                    <x-ui.badge :tone="$tones[$signal['tone']] ?? 'gray'">{{ $signal['label'] }}</x-ui.badge>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $signal['value'] }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $signal['description'] }}</p>
                </x-ui.card>
            @endforeach
        </div>
    @endif
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-ui.card title="SEO metadata coverage" flush>
            @foreach ($marketingHealth['seoCoverage'] as $row)
                <div class="{{ $rowClass }}">
                    <p class="font-medium text-gray-900 dark:text-white">{{ $row['label'] }} · {{ $row['value'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['context'] }}</p>
                </div>
            @endforeach
        </x-ui.card>
        <x-ui.card title="UTM attribution (last 90 days)" flush>
            @forelse ($marketingHealth['attribution'] as $row)
                <div class="{{ $rowClass }}">
                    <p class="font-medium text-gray-900 dark:text-white">{{ $row['label'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['meta'] }}</p>
                </div>
            @empty
                <p class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">No UTM-tagged inquiries in the last 90 days. Tag outbound links with utm_source to start attributing traffic.</p>
            @endforelse
        </x-ui.card>
    </div>

    {{-- Content ops --}}
    <h2 class="mb-1 mt-10 text-lg font-semibold text-gray-900 dark:text-white">Publishing, drafts, and system status</h2>
    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">What's live, what's queued, and how the CMS and import systems are behaving.</p>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($contentOps['stats'] as $stat)
            <x-ui.stat-card :label="$stat['label']" :value="$stat['value']" :hint="$stat['meta']" />
        @endforeach
    </div>
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-ui.card title="System status">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($contentOps['systemPanels'] as $panel)
                    <div>
                        <x-ui.badge :tone="$tones[$panel['tone']] ?? 'gray'">{{ $panel['label'] }}</x-ui.badge>
                        <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $panel['value'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $panel['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
        <x-ui.card title="Drafts &amp; publishing radar" flush>
            @foreach ($contentOps['radar'] as $item)
                <div class="{{ $rowClass }}">
                    <p class="font-medium text-gray-900 dark:text-white">{{ $item['label'] }} · {{ $item['value'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['context'] }}</p>
                </div>
            @endforeach
        </x-ui.card>
    </div>
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-ui.card title="Recent inquiries" flush>
            @forelse ($recentInquiries as $inquiry)
                <div class="{{ $rowClass }}">
                    <p class="font-medium text-gray-900 dark:text-white">{{ $inquiry->primary_name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $inquiry->email }} · {{ str($inquiry->status)->replace('_', ' ')->headline() }}</p>
                </div>
            @empty
                <p class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">No inquiries have been recorded yet.</p>
            @endforelse
            <div class="px-5 py-3"><a class="{{ $link }}" href="{{ route('admin.inquiries.index') }}">Open inquiries →</a></div>
        </x-ui.card>
    </div>
@endsection
