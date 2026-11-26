@extends('layouts.admin-v2')

@section('title', 'Inquiries')
@section('eyebrow', 'Leads')
@section('heading', 'Inquiries')
@section('subheading', 'Review new leads, sort the pipeline, and keep inquiry records current.')
@section('header_actions')
    <x-ui.button :href="route('admin.inquiries.create')">New lead</x-ui.button>
@endsection
@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
        $filterParams = $search !== '' ? ['search' => $search] : [];
        $statusTone = ['new' => 'brand', 'active' => 'amber', 'follow_up' => 'amber', 'booked' => 'green', 'archived' => 'gray'];
        $cols = 'md:grid-cols-[2fr_2fr_1fr_1fr_1fr]';
    @endphp

    <div class="[display:grid] grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($statusSummary as $stat)
            <x-ui.stat-card :label="$stat['label']" :value="$stat['value']" :hint="$stat['meta']" />
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.inquiries.index') }}" class="mt-6">
        <x-ui.card>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <x-ui.field label="Search inquiries" class="flex-1">
                    <input type="search" name="search" value="{{ $search }}" placeholder="Name, email, phone, venue, or city" class="{{ $input }}">
                </x-ui.field>
                @if ($currentStatus !== 'all')
                    <input type="hidden" name="status" value="{{ $currentStatus }}">
                @endif
                <div class="flex items-center gap-3">
                    <x-ui.button type="submit">Apply</x-ui.button>
                    @if ($search !== '' || $currentStatus !== 'all')
                        <a href="{{ route('admin.inquiries.index') }}" class="text-sm text-gray-500 underline dark:text-gray-400">Clear</a>
                    @endif
                </div>
            </div>
        </x-ui.card>
    </form>

    <nav class="mt-6 flex flex-wrap gap-2" aria-label="Inquiry status filters">
        @php
            $pill = 'rounded-full px-3.5 py-1.5 text-sm font-medium transition-colors';
            $on = 'bg-brand-500 text-white';
            $off = 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700 dark:hover:bg-gray-700';
        @endphp
        <a class="{{ $pill }} {{ $currentStatus === 'all' ? $on : $off }}" href="{{ route('admin.inquiries.index', $filterParams) }}">All ({{ $statusCounts['all'] }})</a>
        @foreach ($statusOptions as $status => $label)
            <a class="{{ $pill }} {{ $currentStatus === $status ? $on : $off }}" href="{{ route('admin.inquiries.index', ['status' => $status, ...$filterParams]) }}">{{ $label }} ({{ $statusCounts[$status] }})</a>
        @endforeach
    </nav>

    <x-ui.card flush class="mt-6 overflow-hidden">
        <div class="hidden gap-4 border-b border-gray-100 bg-gray-50 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-800/60 dark:text-gray-400 md:[display:grid] {{ $cols }}">
            <span>Lead</span><span>Event</span><span>Status</span><span>Submitted</span><span>Source</span>
        </div>
        @forelse ($inquiries as $inquiry)
            <div class="relative [display:grid] grid-cols-1 gap-2 border-b border-gray-100 px-5 py-4 text-sm last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60 md:items-center md:gap-4 {{ $cols }}">
                <div class="min-w-0">
                    <a href="{{ route('admin.inquiries.edit', $inquiry) }}" class="block truncate font-medium text-gray-900 after:absolute after:inset-0 dark:text-white">{{ $inquiry->primary_name }}</a>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ collect([$inquiry->email, $inquiry->partner_name, $inquiry->phone])->filter()->implode(' · ') }}
                    </p>
                </div>
                <div class="min-w-0">
                    <p class="truncate font-medium text-gray-800 dark:text-gray-200">{{ $inquiry->eventTypeLabel() }}</p>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ collect([$inquiry->event_date?->format('M j, Y') ?: 'Date not set', $inquiry->venue?->name ?: $inquiry->venue_name, $inquiry->location_city])->filter()->implode(' · ') }}
                    </p>
                </div>
                <div>
                    <x-ui.badge :tone="$statusTone[$inquiry->status] ?? 'gray'">{{ $statusOptions[$inquiry->status] ?? str($inquiry->status)->replace('_', ' ')->headline() }}</x-ui.badge>
                </div>
                <div class="text-gray-500 dark:text-gray-400">{{ $inquiry->created_at?->format('M j, Y g:i A') }}</div>
                <div class="min-w-0">
                    <p class="truncate text-gray-700 dark:text-gray-300">{{ str($inquiry->source)->replace('_', ' ')->headline() }}</p>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ collect([$inquiry->utm_source, $inquiry->utm_medium, $inquiry->utm_campaign])->filter()->implode(' · ') ?: 'No campaign data' }}
                    </p>
                </div>
            </div>
        @empty
            <p class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No inquiries matched the current filters.</p>
        @endforelse
    </x-ui.card>

    @if ($inquiries->hasPages())
        <div class="mt-4">{{ $inquiries->links() }}</div>
    @endif
@endsection
