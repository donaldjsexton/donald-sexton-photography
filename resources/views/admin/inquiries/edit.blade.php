@extends('layouts.admin')

@section('title', 'Inquiry')
@section('eyebrow', 'Leads')
@section('heading', $inquiry->primary_name)
@section('subheading', 'Review inquiry details and move this lead through the studio pipeline.')
@section('header_actions')
    <a class="cta-secondary" href="{{ route('admin.inquiries.index') }}">Back to Inquiries</a>
@endsection
@section('content')
    <section class="admin-grid admin-grid--two">
        <article class="admin-card">
            <p class="eyebrow">Lead Details</p>
            <div class="admin-detail-list">
                <div class="admin-detail-list__item">
                    <strong>Primary contact</strong>
                    <span class="meta">{{ $inquiry->primary_name }}</span>
                </div>
                <div class="admin-detail-list__item">
                    <strong>Partner</strong>
                    <span class="meta">{{ $inquiry->partner_name ?: 'Not provided' }}</span>
                </div>
                <div class="admin-detail-list__item">
                    <strong>Email</strong>
                    <span class="meta">{{ $inquiry->email }}</span>
                </div>
                <div class="admin-detail-list__item">
                    <strong>Phone</strong>
                    <span class="meta">{{ $inquiry->phone ?: 'Not provided' }}</span>
                </div>
                <div class="admin-detail-list__item">
                    <strong>Instagram</strong>
                    <span class="meta">{{ $inquiry->instagram_handle ?: 'Not provided' }}</span>
                </div>
                <div class="admin-detail-list__item">
                    <strong>Heard about us</strong>
                    <span class="meta">{{ $inquiry->heard_about ?: 'Not provided' }}</span>
                </div>
            </div>
        </article>

        <article class="admin-card">
            <p class="eyebrow">Event Details</p>
            <div class="admin-detail-list">
                <div class="admin-detail-list__item">
                    <strong>Event type</strong>
                    <span class="meta">{{ str($inquiry->event_type)->replace('_', ' ')->headline() }}</span>
                </div>
                <div class="admin-detail-list__item">
                    <strong>Event date</strong>
                    <span class="meta">{{ $inquiry->event_date?->format('F j, Y') ?: 'Not provided' }}</span>
                </div>
                <div class="admin-detail-list__item">
                    <strong>Venue</strong>
                    <span class="meta">{{ $inquiry->venue?->name ?: $inquiry->venue_name ?: 'Not provided' }}</span>
                </div>
                <div class="admin-detail-list__item">
                    <strong>City</strong>
                    <span class="meta">{{ $inquiry->location_city ?: 'Not provided' }}</span>
                </div>
                <div class="admin-detail-list__item">
                    <strong>Guest count</strong>
                    <span class="meta">{{ $inquiry->guest_count_range ?: 'Not provided' }}</span>
                </div>
                <div class="admin-detail-list__item">
                    <strong>Budget</strong>
                    <span class="meta">{{ $inquiry->budget_range ?: 'Not provided' }}</span>
                </div>
                <div class="admin-detail-list__item">
                    <strong>Coverage</strong>
                    <span class="meta">{{ collect($inquiry->coverage_interest ?? [])->filter()->join(', ') ?: 'Not provided' }}</span>
                </div>
            </div>
        </article>
    </section>

    <section class="admin-grid admin-grid--two">
        <article class="admin-card">
            <p class="eyebrow">Pipeline</p>

            <form method="POST" action="{{ route('admin.inquiries.update', $inquiry) }}" class="admin-form">
                @csrf
                @method('PUT')

                <label>
                    Status
                    <select name="status">
                        @foreach ($statusOptions as $status => $label)
                            <option value="{{ $status }}" @selected(old('status', $inquiry->status) === $status)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <p class="meta">Use the pipeline status to separate fresh inquiries from active conversations, follow-ups, and booked work.</p>

                <button class="cta" type="submit" style="border: 0; cursor: pointer;">Save Inquiry</button>
            </form>
        </article>

        <article class="admin-card">
            <p class="eyebrow">Source</p>
            <div class="admin-detail-list">
                <div class="admin-detail-list__item">
                    <strong>Source</strong>
                    <span class="meta">{{ str($inquiry->source)->replace('_', ' ')->headline() }}</span>
                </div>
                <div class="admin-detail-list__item">
                    <strong>UTM source</strong>
                    <span class="meta">{{ $inquiry->utm_source ?: 'Not provided' }}</span>
                </div>
                <div class="admin-detail-list__item">
                    <strong>UTM medium</strong>
                    <span class="meta">{{ $inquiry->utm_medium ?: 'Not provided' }}</span>
                </div>
                <div class="admin-detail-list__item">
                    <strong>UTM campaign</strong>
                    <span class="meta">{{ $inquiry->utm_campaign ?: 'Not provided' }}</span>
                </div>
                <div class="admin-detail-list__item">
                    <strong>Submitted</strong>
                    <span class="meta">{{ $inquiry->created_at?->format('F j, Y g:i A') }}</span>
                </div>
            </div>
        </article>
    </section>

    <section class="admin-card">
        <p class="eyebrow">Message</p>
        <p class="section-copy">{{ $inquiry->message ?: 'No message was included with this inquiry.' }}</p>
    </section>
@endsection
