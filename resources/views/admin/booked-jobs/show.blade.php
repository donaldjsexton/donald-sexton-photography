@extends('layouts.admin')

@section('title', $job->couple_names ?: $job->summary)
@section('eyebrow', 'Calendar Event')
@section('heading', $job->couple_names ?: $job->summary)
@section('subheading', $job->event_date->format('l, F j, Y').($job->event_time ? ' · '.$job->event_time : ''))
@section('header_actions')
    <a class="cta-secondary" href="{{ route('admin.booked-jobs.index', ['year' => $job->event_date->year, 'month' => $job->event_date->month]) }}">Back to Calendar</a>
@endsection
@section('content')
    <div class="admin-grid admin-grid--two">
        <section class="admin-card">
            <p class="eyebrow">Details</p>

            <form method="POST" action="{{ route('admin.booked-jobs.update', $job) }}">
                @csrf
                @method('PUT')

                <div class="admin-form-group">
                    <label>
                        <span class="admin-form-label">Couple Names</span>
                        <input type="text" name="couple_names" value="{{ old('couple_names', $job->couple_names) }}">
                    </label>
                </div>

                <div class="admin-form-group">
                    <label>
                        <span class="admin-form-label">Event Time</span>
                        <input type="text" name="event_time" value="{{ old('event_time', $job->event_time) }}" placeholder="e.g. 4:30 PM">
                    </label>
                </div>

                <div class="admin-form-group">
                    <label>
                        <span class="admin-form-label">Location</span>
                        <input type="text" name="location" value="{{ old('location', $job->location) }}">
                    </label>
                </div>

                <div class="admin-form-group">
                    <label>
                        <span class="admin-form-label">Coordinator</span>
                        <input type="text" name="coordinator" value="{{ old('coordinator', $job->coordinator) }}">
                    </label>
                </div>

                <div class="admin-form-group">
                    <label>
                        <span class="admin-form-label">Status</span>
                        <select name="status">
                            @foreach (\App\Models\BookedJob::statusOptions() as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $job->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="admin-form-group">
                    <label>
                        <span class="admin-form-label">Ceremony Notes</span>
                        <textarea name="ceremony_notes" rows="8">{{ old('ceremony_notes', $job->ceremony_notes) }}</textarea>
                    </label>
                </div>

                <button class="cta" type="submit" style="border: 0; cursor: pointer;">Save Changes</button>
            </form>
        </section>

        <section class="admin-card">
            <p class="eyebrow">Sync Info</p>

            <dl class="admin-dl">
                <dt>Google Event ID</dt>
                <dd class="meta">{{ $job->google_event_id }}</dd>

                <dt>Calendar Summary</dt>
                <dd>{{ $job->summary }}</dd>

                <dt>Last Synced</dt>
                <dd>{{ $job->synced_at?->format('M j, Y g:i A') ?: 'Never' }}</dd>

                <dt>Created</dt>
                <dd>{{ $job->created_at?->format('M j, Y g:i A') }}</dd>
            </dl>

            @if ($job->raw_description)
                <details class="admin-details">
                    <summary class="meta">Raw Calendar Description</summary>
                    <pre class="admin-pre">{{ $job->raw_description }}</pre>
                </details>
            @endif
        </section>
    </div>
@endsection
