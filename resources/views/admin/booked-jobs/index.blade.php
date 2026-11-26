@extends('layouts.admin-v2')

@section('title', 'Calendar')
@section('eyebrow', 'Studio')
@section('heading', 'Calendar')
@section('subheading', 'Wedding calendar synced from Google. Refreshes when you load this page.')
@section('content')
    @php
        $monthDate = \Carbon\Carbon::createFromDate($year, $month, 1);
        $prevMonth = $monthDate->copy()->subMonth();
        $nextMonth = $monthDate->copy()->addMonth();
        $muted = 'text-gray-500 dark:text-gray-400';
    @endphp

    <div class="mb-4 flex items-center justify-between gap-3">
        <x-ui.button variant="secondary" :href="route('admin.booked-jobs.index', ['year' => $prevMonth->year, 'month' => $prevMonth->month])">&larr; {{ $prevMonth->format('M') }}</x-ui.button>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $monthDate->format('F Y') }}</h2>
        <x-ui.button variant="secondary" :href="route('admin.booked-jobs.index', ['year' => $nextMonth->year, 'month' => $nextMonth->month])">{{ $nextMonth->format('M') }} &rarr;</x-ui.button>
    </div>

    @if ($lastSyncedAt)
        <p class="mb-4 text-xs {{ $muted }}">Last synced {{ $lastSyncedAt->diffForHumans() }}.</p>
    @endif

    {{-- Desktop calendar grid --}}
    <x-ui.card flush class="hidden overflow-hidden md:block">
        <div class="[display:grid] grid-cols-7">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
                <div class="border-b border-gray-100 bg-gray-50 px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-800/60 dark:text-gray-400">{{ $dayName }}</div>
            @endforeach

            @foreach ($calendarDays as $day)
                <div class="min-h-28 border-b border-r border-gray-100 p-1.5 dark:border-gray-800 {{ $day['inMonth'] ? '' : 'bg-gray-50/70 opacity-60 dark:bg-gray-900/40' }} {{ $day['isToday'] ? 'bg-brand-50/60 dark:bg-brand-500/10' : '' }}">
                    <span class="mb-1 block text-xs font-medium {{ $day['isToday'] ? 'text-brand-600 dark:text-brand-300' : $muted }}">{{ $day['day'] }}</span>
                    @foreach ($day['jobs'] as $job)
                        <a href="{{ route('admin.booked-jobs.show', $job) }}" class="mb-1 block truncate rounded-md px-1.5 py-1 text-xs font-medium {{ $job->isCancelled() ? 'bg-gray-100 text-gray-500 line-through dark:bg-gray-700/60 dark:text-gray-400' : 'bg-brand-50 text-brand-600 hover:bg-brand-100 dark:bg-brand-500/15 dark:text-brand-300' }}">
                            {{ Str::limit($job->couple_names ?: $job->summary, 20) }}
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </x-ui.card>

    {{-- Mobile list view --}}
    <x-ui.card flush class="overflow-hidden md:hidden">
        @forelse ($jobs->where('status', '!=', 'cancelled') as $job)
            <a href="{{ route('admin.booked-jobs.show', $job) }}" class="flex gap-4 border-b border-gray-100 px-5 py-3 text-sm last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60">
                <div class="w-14 shrink-0">
                    <strong class="block text-gray-900 dark:text-white">{{ $job->event_date?->format('M j') ?: 'TBD' }}</strong>
                    <span class="text-xs {{ $muted }}">{{ $job->event_date?->format('D') }}</span>
                </div>
                <div class="min-w-0">
                    <strong class="block text-gray-900 dark:text-white">{{ $job->couple_names ?: $job->summary }}</strong>
                    <span class="text-xs {{ $muted }}">
                        {{ $job->event_time ?: 'Time TBD' }}
                        @if ($job->location) &middot; {{ $job->location }} @endif
                    </span>
                </div>
            </a>
        @empty
            <p class="px-5 py-8 text-center text-sm {{ $muted }}">No booked jobs this month.</p>
        @endforelse
    </x-ui.card>

    @if ($awaitingDate->isNotEmpty())
        <x-ui.card title="Awaiting a Date" flush class="mt-6">
            <p class="px-5 pt-4 text-sm {{ $muted }}">Booked work with no agreed date yet. These are not on the calendar.</p>
            @foreach ($awaitingDate as $job)
                <a href="{{ route('admin.booked-jobs.show', $job) }}" class="block border-b border-gray-100 px-5 py-3 text-sm last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60">
                    <span class="block font-medium text-gray-900 dark:text-white">{{ $job->couple_names ?: $job->summary }}</span>
                    <span class="block text-xs {{ $muted }}">{{ $job->confirmationLabel() }}</span>
                </a>
            @endforeach
        </x-ui.card>
    @endif

    @if ($upcoming->isNotEmpty())
        <x-ui.card title="Next Up" flush class="mt-6">
            @foreach ($upcoming as $job)
                <div class="border-b border-gray-100 px-5 py-3 text-sm last:border-0 dark:border-gray-800">
                    <span class="block font-medium text-gray-900 dark:text-white">{{ ($job->couple_names ?: $job->summary).' · '.($job->event_date?->format('M j, Y') ?: 'Date TBD') }}</span>
                    <span class="block text-xs {{ $muted }}">{{ collect([$job->event_time, $job->location, $job->coordinator])->filter()->implode(' · ') }}</span>
                </div>
            @endforeach
        </x-ui.card>
    @endif
@endsection
