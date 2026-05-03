@extends('layouts.admin')

@section('title', 'Inquiries')
@section('eyebrow', 'Leads')
@section('heading', 'Inquiries')
@section('subheading', 'Review new leads, sort the pipeline, and keep inquiry records current.')
@section('header_actions')
    <a class="cta" href="{{ route('admin.inquiries.create') }}">New Lead</a>
@endsection
@section('content')
    @php($filterParams = $search !== '' ? ['search' => $search] : [])

    <section class="admin-stat-grid">
        @foreach ($statusSummary as $stat)
            <article class="admin-card admin-card--metric">
                <p class="eyebrow">{{ $stat['label'] }}</p>
                <p class="admin-stat">{{ $stat['value'] }}</p>
                <p class="meta">{{ $stat['meta'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="admin-card">
        <form method="GET" action="{{ route('admin.inquiries.index') }}" class="admin-search-form">
            <label>
                Search inquiries
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Name, email, phone, venue, or city"
                >
            </label>

            @if ($currentStatus !== 'all')
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif

            <button class="cta" type="submit" style="border: 0; cursor: pointer;">Apply</button>

            @if ($search !== '' || $currentStatus !== 'all')
                <a class="cta-secondary" href="{{ route('admin.inquiries.index') }}">Clear</a>
            @endif
        </form>
    </section>

    <nav class="admin-section-nav" aria-label="Inquiry status filters">
        <a class="{{ $currentStatus === 'all' ? 'is-active' : '' }}" href="{{ route('admin.inquiries.index', $filterParams) }}">
            All ({{ $statusCounts['all'] }})
        </a>

        @foreach ($statusOptions as $status => $label)
            <a
                class="{{ $currentStatus === $status ? 'is-active' : '' }}"
                href="{{ route('admin.inquiries.index', ['status' => $status, ...$filterParams]) }}"
            >
                {{ $label }} ({{ $statusCounts[$status] }})
            </a>
        @endforeach
    </nav>

    <div class="admin-table-wrap">
        <table class="admin-table admin-table--cards">
            <thead>
                <tr>
                    <th>Lead</th>
                    <th>Event</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Source</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($inquiries as $inquiry)
                    <tr>
                        <td class="inquiries-col--lead">
                            <div class="admin-table__lead">
                                <strong>{{ $inquiry->primary_name }}</strong>
                                <span class="meta">
                                    {{ $inquiry->email }}
                                    @if ($inquiry->partner_name)
                                        · {{ $inquiry->partner_name }}
                                    @endif
                                    @if ($inquiry->phone)
                                        · {{ $inquiry->phone }}
                                    @endif
                                </span>
                            </div>
                        </td>
                        <td class="inquiries-col--event">
                            <div class="admin-table__lead">
                                <strong>{{ str($inquiry->event_type)->replace('_', ' ')->headline() }}</strong>
                                <span class="meta">
                                    {{ $inquiry->event_date?->format('M j, Y') ?: 'Date not set' }}
                                    @if ($inquiry->venue?->name || $inquiry->venue_name)
                                        · {{ $inquiry->venue?->name ?: $inquiry->venue_name }}
                                    @endif
                                    @if ($inquiry->location_city)
                                        · {{ $inquiry->location_city }}
                                    @endif
                                </span>
                            </div>
                        </td>
                        <td class="inquiries-col--status">
                            <span class="admin-status-pill admin-status-pill--{{ str_replace('_', '-', $inquiry->status) }}">
                                {{ $statusOptions[$inquiry->status] ?? str($inquiry->status)->replace('_', ' ')->headline() }}
                            </span>
                        </td>
                        <td class="inquiries-col--submitted">{{ $inquiry->created_at?->format('M j, Y g:i A') }}</td>
                        <td class="inquiries-col--source">
                            <div class="admin-table__lead">
                                <strong>{{ str($inquiry->source)->replace('_', ' ')->headline() }}</strong>
                                <span class="meta">
                                    @if ($inquiry->utm_source || $inquiry->utm_medium || $inquiry->utm_campaign)
                                        {{ collect([$inquiry->utm_source, $inquiry->utm_medium, $inquiry->utm_campaign])->filter()->implode(' · ') }}
                                    @else
                                        No campaign data
                                    @endif
                                </span>
                            </div>
                        </td>
                        <td class="inquiries-col--open"><a href="{{ route('admin.inquiries.edit', $inquiry) }}">Open</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No inquiries matched the current filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($inquiries->hasPages())
        <div class="pagination">
            {{ $inquiries->links() }}
        </div>
    @endif
@endsection
