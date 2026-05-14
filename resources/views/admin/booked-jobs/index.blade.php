@extends('layouts.admin')

@section('title', 'Calendar')
@section('eyebrow', 'Studio')
@section('heading', 'Calendar')
@section('subheading', 'Wedding calendar synced from Google. Refreshes when you load this page.')
@section('content')
    @php
        $monthDate = \Carbon\Carbon::createFromDate($year, $month, 1);
        $prevMonth = $monthDate->copy()->subMonth();
        $nextMonth = $monthDate->copy()->addMonth();
    @endphp

    <section class="cal-controls">
        <a class="cta-secondary" href="{{ route('admin.booked-jobs.index', ['year' => $prevMonth->year, 'month' => $prevMonth->month]) }}">
            &larr; {{ $prevMonth->format('M') }}
        </a>
        <h3 class="cal-month-label">{{ $monthDate->format('F Y') }}</h3>
        <a class="cta-secondary" href="{{ route('admin.booked-jobs.index', ['year' => $nextMonth->year, 'month' => $nextMonth->month]) }}">
            {{ $nextMonth->format('M') }} &rarr;
        </a>
    </section>

    @if ($lastSyncedAt)
        <p class="meta cal-sync-status">Last synced {{ $lastSyncedAt->diffForHumans() }}.</p>
    @endif

    {{-- Desktop calendar grid --}}
    <section class="cal-grid-wrap">
        <div class="cal-grid">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
                <div class="cal-day-header">{{ $dayName }}</div>
            @endforeach

            @foreach ($calendarDays as $day)
                <div class="cal-cell {{ $day['inMonth'] ? '' : 'cal-cell--outside' }} {{ $day['isToday'] ? 'cal-cell--today' : '' }}">
                    <span class="cal-cell__day">{{ $day['day'] }}</span>
                    @foreach ($day['jobs'] as $job)
                        <a href="{{ route('admin.booked-jobs.show', $job) }}" class="cal-event {{ $job->isCancelled() ? 'cal-event--cancelled' : '' }}">
                            {{ Str::limit($job->couple_names ?: $job->summary, 20) }}
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>

    {{-- Mobile list view --}}
    <section class="cal-list-wrap">
        @forelse ($jobs->where('status', '!=', 'cancelled') as $job)
            <a href="{{ route('admin.booked-jobs.show', $job) }}" class="cal-list-item">
                <div class="cal-list-item__date">
                    <strong>{{ $job->event_date->format('M j') }}</strong>
                    <span class="meta">{{ $job->event_date->format('D') }}</span>
                </div>
                <div class="cal-list-item__details">
                    <strong>{{ $job->couple_names ?: $job->summary }}</strong>
                    <span class="meta">
                        {{ $job->event_time ?: 'Time TBD' }}
                        @if ($job->location) &middot; {{ $job->location }} @endif
                    </span>
                </div>
            </a>
        @empty
            <p class="meta" style="padding: 1rem;">No booked jobs this month.</p>
        @endforelse
    </section>

    @if ($upcoming->isNotEmpty())
        <section class="admin-card" style="margin-top: 2rem;">
            <p class="eyebrow">Next Up</p>
            <x-admin.list>
                @foreach ($upcoming as $job)
                    <x-admin.list-item
                        :title="($job->couple_names ?: $job->summary).' · '.$job->event_date->format('M j, Y')"
                        :meta="collect([$job->event_time, $job->location, $job->coordinator])->filter()->implode(' · ')"
                    />
                @endforeach
            </x-admin.list>
        </section>
    @endif
@endsection
