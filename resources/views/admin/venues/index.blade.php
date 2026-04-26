@extends('layouts.admin')

@section('title', 'Venues')
@section('eyebrow', 'Content')
@section('heading', 'Venues')
@section('subheading', 'Manage venue records used by inquiries, journal posts, and venue-referral automation.')
@section('header_actions')
    <a class="cta" href="{{ route('admin.venues.create') }}">New Venue</a>
@endsection
@section('content')
    <section class="admin-stat-grid">
        <article class="admin-card admin-card--metric">
            <p class="eyebrow">Total venues</p>
            <p class="admin-stat">{{ $totals['total'] }}</p>
            <p class="meta">All records, including imports.</p>
        </article>
        <article class="admin-card admin-card--metric">
            <p class="eyebrow">Public on /venues</p>
            <p class="admin-stat">{{ $totals['public'] }}</p>
            <p class="meta">Have at least one published story or journal post.</p>
        </article>
        <article class="admin-card admin-card--metric">
            <p class="eyebrow">Hidden stubs</p>
            <p class="admin-stat">{{ $totals['stub'] }}</p>
            <p class="meta">No published content; excluded from the public index and noindexed.</p>
        </article>
    </section>

    <section class="admin-card">
        <form method="GET" action="{{ route('admin.venues.index') }}" class="admin-search-form">
            <label>
                Search venues
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Name, city, state, or region"
                >
            </label>

            <button class="cta" type="submit" style="border: 0; cursor: pointer;">Apply</button>

            @if ($search !== '')
                <a class="cta-secondary" href="{{ route('admin.venues.index') }}">Clear</a>
            @endif
        </form>
    </section>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Location</th>
                    <th>Visibility</th>
                    <th>Referral contact</th>
                    <th>Stories</th>
                    <th>Journal</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($venues as $venue)
                    <tr>
                        <td>
                            <div class="admin-table__lead">
                                <strong>{{ $venue->name }}</strong>
                                <span class="meta">{{ $venue->slug }}</span>
                            </div>
                        </td>
                        <td>
                            {{ collect([$venue->city, $venue->state, $venue->region])->filter()->implode(' · ') ?: '—' }}
                        </td>
                        <td>
                            @php
                                $isStub = ($venue->published_wedding_stories_count ?? 0) === 0
                                    && ($venue->published_journal_posts_count ?? 0) === 0;
                            @endphp
                            @if ($isStub)
                                <span class="admin-status-pill admin-status-pill--archived">Hidden</span>
                                <span class="meta">No published content</span>
                            @elseif ($venue->is_featured)
                                <span class="admin-status-pill admin-status-pill--active">Featured</span>
                            @else
                                <span class="admin-status-pill admin-status-pill--new">Public</span>
                            @endif
                        </td>
                        <td>
                            @if ($venue->referral_contact_name || ! empty($venue->referral_emails))
                                <div class="admin-table__lead">
                                    <strong>{{ $venue->referral_contact_name ?: '—' }}</strong>
                                    <span class="meta">
                                        {{ ! empty($venue->referral_emails) ? implode(', ', $venue->referral_emails) : 'No referral emails' }}
                                    </span>
                                </div>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $venue->wedding_stories_count }}</td>
                        <td>{{ $venue->journal_posts_count }}</td>
                        <td><a href="{{ route('admin.venues.edit', $venue) }}">Edit</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">No venues match the current filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">
        {{ $venues->links() }}
    </div>
@endsection
