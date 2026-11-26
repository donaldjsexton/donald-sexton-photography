@extends('layouts.admin-v2')

@section('title', $job->couple_names ?: $job->summary)
@section('eyebrow', 'Calendar Event')
@section('heading', $job->couple_names ?: $job->summary)
@section('subheading', ($job->event_date?->format('l, F j, Y') ?: 'Date to be decided').($job->event_time ? ' · '.$job->event_time : '').' · '.$job->confirmationLabel())
@section('header_actions')
    <x-ui.button variant="secondary" :href="route('admin.booked-jobs.index', $job->event_date ? ['year' => $job->event_date->year, 'month' => $job->event_date->month] : [])">Back to Calendar</x-ui.button>
@endsection
@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none disabled:opacity-60 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
        $muted = 'text-sm text-gray-500 dark:text-gray-400';
        $dt = 'text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
        $dd = 'mb-3 text-sm text-gray-900 dark:text-gray-100';
    @endphp

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="space-y-6">
            <x-ui.card title="Details">
                <form method="POST" action="{{ route('admin.booked-jobs.update', $job) }}" class="max-w-none! space-y-4">
                    @csrf
                    @method('PUT')

                    <x-ui.field label="Couple Names">
                        <input class="{{ $input }}" type="text" name="couple_names" value="{{ old('couple_names', $job->couple_names) }}">
                    </x-ui.field>

                    <div>
                        <x-ui.field label="Event Date">
                            <input class="{{ $input }}" type="date" name="event_date" value="{{ old('event_date', $job->event_date?->toDateString()) }}" @disabled($job->isDateLocked())>
                        </x-ui.field>
                        @if ($job->isDateLocked())
                            <p class="mt-1.5 {{ $muted }}">Locked by a signed contract. Use Reschedule below to move it.</p>
                        @else
                            <p class="mt-1.5 {{ $muted }}">Leave blank while the date is still to be decided.</p>
                        @endif
                    </div>

                    <x-ui.field label="Event Time">
                        <input class="{{ $input }}" type="text" name="event_time" value="{{ old('event_time', $job->event_time) }}" placeholder="e.g. 4:30 PM">
                    </x-ui.field>

                    <x-ui.field label="Location">
                        <input class="{{ $input }}" type="text" name="location" value="{{ old('location', $job->location) }}">
                    </x-ui.field>

                    <x-ui.field label="Coordinator">
                        <input class="{{ $input }}" type="text" name="coordinator" value="{{ old('coordinator', $job->coordinator) }}">
                    </x-ui.field>

                    <x-ui.field label="Status">
                        <select class="{{ $input }}" name="status">
                            @foreach (\App\Models\BookedJob::statusOptions() as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $job->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>

                    <x-ui.field label="Ceremony Notes">
                        <textarea class="{{ $input }}" name="ceremony_notes" rows="8">{{ old('ceremony_notes', $job->ceremony_notes) }}</textarea>
                    </x-ui.field>

                    <x-ui.button type="submit">Save Changes</x-ui.button>
                </form>
            </x-ui.card>
        </div>

        <div class="space-y-6">
            <x-ui.card title="Booking Status">
                <dl>
                    <dt class="{{ $dt }}">Confirmation</dt>
                    <dd class="{{ $dd }}">{{ $job->confirmationLabel() }}</dd>

                    <dt class="{{ $dt }}">Signed Contract</dt>
                    <dd class="{{ $dd }}">{{ $job->hasSignedContract() ? 'Yes' : 'Not yet' }}</dd>

                    <dt class="{{ $dt }}">Retainer Paid</dt>
                    <dd class="{{ $dd }}">{{ $job->retainerPaid() ? 'Yes' : 'Not yet' }}</dd>
                </dl>

                <p class="{{ $muted }}">A booking is only final once the contract is signed and the retainer is paid. This is read from the attached contracts and invoices, so it is never out of date.</p>

                @if ($job->rescheduled_at)
                    <p class="mt-4 {{ $muted }}">
                        Rescheduled {{ $job->rescheduled_at->format('M j, Y') }}
                        @if ($job->previous_event_date)
                            from {{ $job->previous_event_date->format('F j, Y') }}
                        @endif
                        — {{ $job->reschedule_reason }}
                    </p>

                    @if ($job->contractsNeedingAmendment()->isNotEmpty())
                        <p class="mt-2 {{ $muted }}">
                            <strong>{{ $job->contractsNeedingAmendment()->count() }}</strong> signed
                            {{ Str::plural('contract', $job->contractsNeedingAmendment()->count()) }}
                            still {{ $job->contractsNeedingAmendment()->count() === 1 ? 'quotes' : 'quote' }}
                            the old date and need an amendment.
                        </p>
                    @endif
                @endif

                @if ($job->isDateLocked())
                    <details class="mt-6">
                        <summary class="cursor-pointer {{ $muted }}">Reschedule this date</summary>

                        <form method="POST" action="{{ route('admin.booked-jobs.reschedule', $job) }}" class="max-w-none! mt-4 space-y-4">
                            @csrf
                            @method('PUT')

                            <x-ui.field label="New Event Date">
                                <input class="{{ $input }}" type="date" name="event_date" value="{{ old('event_date', $job->event_date?->toDateString()) }}" required>
                            </x-ui.field>

                            <x-ui.field label="Reason">
                                <textarea class="{{ $input }}" name="reschedule_reason" rows="3" required>{{ old('reschedule_reason') }}</textarea>
                            </x-ui.field>

                            <x-ui.button type="submit">Reschedule</x-ui.button>
                        </form>
                    </details>
                @endif
            </x-ui.card>

            <x-ui.card title="Sync Info">
                <dl>
                    <dt class="{{ $dt }}">Google Event ID</dt>
                    <dd class="{{ $dd }} break-all">{{ $job->google_event_id }}</dd>

                    <dt class="{{ $dt }}">Event Type</dt>
                    <dd class="{{ $dd }}">{{ $job->eventTypeLabel() }}</dd>

                    <dt class="{{ $dt }}">Calendar Summary</dt>
                    <dd class="{{ $dd }}">{{ $job->summary }}</dd>

                    <dt class="{{ $dt }}">Last Synced</dt>
                    <dd class="{{ $dd }}">{{ $job->synced_at?->format('M j, Y g:i A') ?: 'Never' }}</dd>

                    <dt class="{{ $dt }}">Created</dt>
                    <dd class="{{ $dd }}">{{ $job->created_at?->format('M j, Y g:i A') }}</dd>
                </dl>

                @if ($job->raw_description)
                    <details>
                        <summary class="cursor-pointer {{ $muted }}">Raw Calendar Description</summary>
                        <pre class="mt-2 overflow-x-auto whitespace-pre-wrap rounded-lg bg-gray-50 p-3 text-xs text-gray-700 dark:bg-gray-900 dark:text-gray-300">{{ $job->raw_description }}</pre>
                    </details>
                @endif
            </x-ui.card>
        </div>
    </div>
@endsection
