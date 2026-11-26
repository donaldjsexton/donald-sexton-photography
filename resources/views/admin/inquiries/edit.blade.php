@extends('layouts.admin-v2')

@section('title', 'Inquiry')
@section('eyebrow', 'Leads')
@section('heading', $inquiry->primary_name)
@section('subheading', 'Review inquiry details and move this lead through the studio pipeline.')
@section('header_actions')
    <x-ui.button variant="secondary" :href="route('admin.inquiries.index')">Back to Inquiries</x-ui.button>
@endsection
@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
        $dt = 'text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
        $dd = 'mt-0.5 text-sm text-gray-800 dark:text-gray-200';
        $meta = 'text-sm text-gray-500 dark:text-gray-400';
    @endphp
    @if (session('error'))
        <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">{{ session('error') }}</div>
    @endif

    @if ($awaitingApproval)
        <div class="mb-6 rounded-xl border border-amber-200 border-l-4 border-l-amber-500 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
            <strong>Awaiting your approval — nothing has been sent to the couple.</strong>
            <p class="mt-1.5 text-sm">
                @if ($isGatedReferral)
                    This arrived as an availability request from {{ $inquiry->venue?->name ?: 'a coordinator' }}. Confirm availability with the venue first — no message reaches the couple until you send one below. Use “Generate draft reply” to start an editable intro.
                @else
                    Review the auto-extracted details below before reaching out. Use “Generate draft reply” to start an editable intro.
                @endif
            </p>
        </div>
    @endif

    <section class="mb-6 [display:grid] grid-cols-1 gap-6 lg:grid-cols-2">
        <x-ui.card>
            <p class="mb-4 text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-400">Lead Details</p>
            <dl class="[display:grid] grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="min-w-0">
                    <dt class="{{ $dt }}">Primary contact</dt>
                    <dd class="{{ $dd }}">{{ $inquiry->primary_name }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="{{ $dt }}">Partner</dt>
                    <dd class="{{ $dd }}">{{ $inquiry->partner_name ?: 'Not provided' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="{{ $dt }}">Email</dt>
                    <dd class="{{ $dd }}">{{ $inquiry->email }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="{{ $dt }}">Phone</dt>
                    <dd class="{{ $dd }}">{{ $inquiry->phone ?: 'Not provided' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="{{ $dt }}">Instagram</dt>
                    <dd class="{{ $dd }}">{{ $inquiry->instagram_handle ?: 'Not provided' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="{{ $dt }}">Lead source</dt>
                    <dd class="{{ $dd }}">{{ $inquiry->leadSourceLabel() }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="{{ $dt }}">Heard about us</dt>
                    <dd class="{{ $dd }}">{{ $inquiry->heard_about ?: 'Not provided' }}</dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card>
            <p class="mb-4 text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-400">Event Details</p>
            <dl class="[display:grid] grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="min-w-0">
                    <dt class="{{ $dt }}">Event type</dt>
                    <dd class="{{ $dd }}">{{ $inquiry->eventTypeLabel() }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="{{ $dt }}">Event date</dt>
                    <dd class="{{ $dd }}">
                        {{ $inquiry->effectiveEventDate()?->format('F j, Y') ?: 'To be decided' }}
                        @if ($inquiry->bookedJob)
                            &middot; {{ $inquiry->bookedJob->confirmationLabel() }}
                        @endif
                    </dd>
                </div>
                <div class="min-w-0">
                    <dt class="{{ $dt }}">Venue</dt>
                    <dd class="{{ $dd }}">{{ $inquiry->venue?->name ?: $inquiry->venue_name ?: 'Not provided' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="{{ $dt }}">City</dt>
                    <dd class="{{ $dd }}">{{ $inquiry->location_city ?: 'Not provided' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="{{ $dt }}">Guest count</dt>
                    <dd class="{{ $dd }}">{{ $inquiry->guest_count_range ?: 'Not provided' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="{{ $dt }}">Budget</dt>
                    <dd class="{{ $dd }}">{{ $inquiry->budget_range ?: 'Not provided' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="{{ $dt }}">Coverage</dt>
                    <dd class="{{ $dd }}">{{ collect($inquiry->coverage_interest ?? [])->filter()->join(', ') ?: 'Not provided' }}</dd>
                </div>
            </dl>
        </x-ui.card>
    </section>

    <section class="mb-6 [display:grid] grid-cols-1 gap-6 lg:grid-cols-2">
        <x-ui.card>
            <p class="mb-4 text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-400">Pipeline</p>

            <form method="POST" action="{{ route('admin.inquiries.update', $inquiry) }}" class="max-w-none! space-y-4">
                @csrf
                @method('PUT')

                <x-ui.field label="Status">
                    <select class="{{ $input }}" name="status">
                        @foreach ($statusOptions as $status => $label)
                            <option value="{{ $status }}" @selected(old('status', $inquiry->status) === $status)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Event type">
                    <select class="{{ $input }}" name="event_type">
                        @foreach (\App\Models\Inquiry::eventTypeOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(old('event_type', $inquiry->event_type) === $value)>{{ $label }}</option>
                        @endforeach
                        @unless (array_key_exists((string) $inquiry->event_type, \App\Models\Inquiry::eventTypeOptions()))
                            <option value="{{ $inquiry->event_type }}" selected>{{ $inquiry->eventTypeLabel() }} (as submitted)</option>
                        @endunless
                    </select>
                </x-ui.field>

                <x-ui.field label="Event date">
                    <input class="{{ $input }}" type="date" name="event_date" value="{{ old('event_date', $inquiry->event_date?->toDateString()) }}" @disabled($inquiry->bookedJob?->isDateLocked())>
                </x-ui.field>

                @if ($inquiry->bookedJob?->isDateLocked())
                    <p class="{{ $meta }}">This date is locked by a signed contract. Use <a class="text-brand-600 underline dark:text-brand-400" href="{{ route('admin.booked-jobs.show', $inquiry->bookedJob) }}">Reschedule on the booked job</a> to move it.</p>
                @else
                    <p class="{{ $meta }}">Leave blank if the date is still to be decided — booking still works, the job just will not hit the calendar yet.</p>
                @endif

                <p class="{{ $meta }}">Use the pipeline status to separate fresh inquiries from active conversations, follow-ups, and booked work.</p>

                <x-ui.button type="submit">Save Inquiry</x-ui.button>
            </form>

            <div class="mt-6 space-y-3 border-t border-gray-100 pt-6 dark:border-gray-800">
                @if ($inquiry->client)
                    <p class="{{ $meta }}">Linked client:</p>
                    <x-ui.button variant="secondary" :href="route('admin.clients.show', $inquiry->client)">View Client → {{ $inquiry->client->displayName() }}</x-ui.button>
                @else
                    <form method="POST" action="{{ route('admin.clients.convert-from-inquiry', $inquiry) }}" class="max-w-none! space-y-3">
                        @csrf
                        <p class="{{ $meta }}">Ready to invoice? Convert this inquiry into a client to issue invoices and accept payments.</p>
                        <x-ui.button type="submit">Convert to Client</x-ui.button>
                    </form>
                @endif
            </div>

            <form method="POST" action="{{ route('admin.inquiries.destroy', $inquiry) }}" class="mt-6 max-w-none! space-y-3 border-t border-gray-100 pt-6 dark:border-gray-800" onsubmit="return confirm('Delete this inquiry permanently? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <p class="{{ $meta }}">Delete this lead if it arrived as spam or in error. This removes the inquiry and all its messages.</p>
                <x-ui.button variant="danger" type="submit">Delete Inquiry</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card>
            <p class="mb-4 text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-400">Source</p>
            <dl class="[display:grid] grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="min-w-0">
                    <dt class="{{ $dt }}">Source</dt>
                    <dd class="{{ $dd }}">{{ str($inquiry->source)->replace('_', ' ')->headline() }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="{{ $dt }}">UTM source</dt>
                    <dd class="{{ $dd }}">{{ $inquiry->utm_source ?: 'Not provided' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="{{ $dt }}">UTM medium</dt>
                    <dd class="{{ $dd }}">{{ $inquiry->utm_medium ?: 'Not provided' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="{{ $dt }}">UTM campaign</dt>
                    <dd class="{{ $dd }}">{{ $inquiry->utm_campaign ?: 'Not provided' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="{{ $dt }}">Submitted</dt>
                    <dd class="{{ $dd }}">{{ $inquiry->created_at?->format('F j, Y g:i A') }}</dd>
                </div>
                @if ($inquiry->first_responded_at)
                    <div class="min-w-0">
                    <dt class="{{ $dt }}">First response</dt>
                    <dd class="{{ $dd }}">{{ $inquiry->first_responded_at->format('F j, Y g:i A') }} ({{ $inquiry->first_responded_at->diffForHumans($inquiry->created_at, \Carbon\CarbonInterface::DIFF_ABSOLUTE) }})</dd>
                </div>
                @endif
            </dl>
        </x-ui.card>
    </section>

    <x-ui.card class="mb-6">
        <p class="mb-4 text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-400">Wedding Questionnaire</p>

        @if ($inquiry->questionnaire)
            @php $q = $inquiry->questionnaire; @endphp
            <dl class="[display:grid] grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="min-w-0">
                    <dt class="{{ $dt }}">Status</dt>
                    <dd class="{{ $dd }}">{{ $q->isSubmitted() ? 'Submitted '.$q->submitted_at->format('F j, Y g:i A') : 'Link generated — awaiting response' }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="{{ $dt }}">Shareable link</dt>
                    <dd class="{{ $dd }} break-all"><a class="text-brand-600 underline dark:text-brand-400" href="{{ $q->publicUrl() }}" target="_blank" rel="noopener">{{ $q->publicUrl() }}</a></dd>
                </div>
            </dl>

            @if ($q->isSubmitted())
                <div class="mt-4">
                    <x-ui.button :href="route('admin.inquiries.questionnaire.show', $inquiry)">View Responses</x-ui.button>
                </div>
            @endif
        @else
            @if ($inquiry->isWedding())
                <p class="{{ $meta }}">No questionnaire has been sent yet. Generate a link to share with the couple.</p>
                <form method="POST" action="{{ route('admin.inquiries.questionnaire.generate', $inquiry) }}" class="mt-4 max-w-none!">
                    @csrf
                    <x-ui.button type="submit">Generate Questionnaire Link</x-ui.button>
                </form>
            @else
                <p class="{{ $meta }}">This questionnaire asks about ceremonies, receptions and first looks, so it is only offered for weddings and elopements. This inquiry is a {{ $inquiry->eventTypeLabel() }} shoot — correct the event type above if that is wrong.</p>
            @endif
        @endif
    </x-ui.card>

    <x-ui.card class="mb-6">
        <p class="mb-4 text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-400">Conversations</p>

        @php
            $threadGroups = $inquiry->messages
                ->groupBy(fn ($msg) => $msg->gmail_thread_id ?: 'local:'.$msg->id)
                ->map(function ($msgs) {
                    $ordered = $msgs->sortBy(fn ($m) => $m->sent_at ?? $m->created_at)->values();
                    $rawSubject = optional($ordered->firstWhere(fn ($m) => filled($m->subject)))->subject;
                    $subject = $rawSubject
                        ? trim(preg_replace('/^(re|fwd|fw):\s*/i', '', (string) $rawSubject))
                        : null;

                    return [
                        'subject' => $subject ?: 'Conversation',
                        'last_at' => $ordered->max(fn ($m) => $m->sent_at ?? $m->created_at),
                        'messages' => $ordered,
                    ];
                })
                ->sortByDesc('last_at')
                ->values();

            $initialAt = $inquiry->created_at;
        @endphp

        <div class="mb-6 space-y-4 inquiry-threads">
            @foreach ($threadGroups as $thread)
                <article class="inquiry-thread rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <header class="inquiry-thread__header mb-3 flex flex-wrap items-baseline justify-between gap-2">
                        <strong class="inquiry-thread__subject text-sm text-gray-900 dark:text-white">{{ $thread['subject'] }}</strong>
                        <span class="{{ $meta }}">
                            {{ $thread['messages']->count() }} {{ Str::plural('message', $thread['messages']->count()) }}
                            · last activity {{ optional($thread['last_at'])->format('M j, Y g:i A') }}
                        </span>
                    </header>

                    <div class="inquiry-timeline space-y-3">
                        @foreach ($thread['messages'] as $msg)
                            @php $stamp = $msg->sent_at ?? $msg->created_at; @endphp
                            <div class="inquiry-timeline__entry inquiry-timeline__entry--{{ $msg->direction }} rounded-lg px-3 py-2 text-sm {{ $msg->direction === 'outbound' ? 'bg-brand-50 dark:bg-brand-500/10' : 'bg-gray-50 dark:bg-gray-800' }}">
                                <div class="inquiry-timeline__meta mb-1 flex flex-wrap items-baseline justify-between gap-2">
                                    <strong>{{ $msg->direction === 'outbound' ? ($msg->sender_name ?: 'Studio') : ($msg->sender_name ?: $inquiry->primary_name) }}</strong>
                                    <span class="{{ $meta }}">{{ optional($stamp)->format('M j, Y g:i A') }}</span>
                                </div>
                                <div class="inquiry-timeline__body whitespace-pre-line text-gray-800 dark:text-gray-200">{{ $msg->body }}</div>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach

            <article class="inquiry-thread inquiry-thread--initial rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <header class="inquiry-thread__header mb-3 flex flex-wrap items-baseline justify-between gap-2">
                    <strong class="inquiry-thread__subject text-sm text-gray-900 dark:text-white">Initial inquiry</strong>
                    <span class="{{ $meta }}">submitted {{ optional($initialAt)->format('M j, Y g:i A') }}</span>
                </header>
                <div class="inquiry-timeline space-y-3">
                    <div class="inquiry-timeline__entry inquiry-timeline__entry--inbound rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-gray-800">
                        <div class="inquiry-timeline__meta mb-1 flex flex-wrap items-baseline justify-between gap-2">
                            <strong>{{ $inquiry->primary_name }}</strong>
                            <span class="{{ $meta }}">{{ optional($initialAt)->format('M j, Y g:i A') }} · form submission</span>
                        </div>
                        <div class="inquiry-timeline__body whitespace-pre-line text-gray-800 dark:text-gray-200">{{ $inquiry->message ?: 'No message was included.' }}</div>
                    </div>
                </div>
            </article>
        </div>

        <form method="POST" action="{{ route('admin.inquiries.draft-reply', $inquiry) }}" class="mb-4 flex max-w-none! flex-wrap items-center gap-3">
            @csrf
            <x-ui.button variant="secondary" type="submit">Generate draft reply</x-ui.button>
            <span class="{{ $meta }}">Pre-fills an editable couple intro you can revise before sending.</span>
        </form>

        <form
            method="POST"
            action="{{ route('admin.inquiries.reply', $inquiry) }}"
            class="inquiry-reply-form max-w-none! space-y-3"
            data-prevent-double-submit
        >
            @csrf
            <x-ui.field label="Reply to {{ $inquiry->primary_name }}">
                <textarea class="{{ $input }}" name="body" rows="4" required placeholder="Write your reply...">{{ old('body', session('draft_body')) }}</textarea>
            </x-ui.field>
            @error('body')
                <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
            <div class="inquiry-reply-form__actions flex items-center gap-3">
                <x-ui.button type="submit" data-sending-label="Sending...">Send Reply</x-ui.button>
                <span class="{{ $meta }}">Sends to {{ $inquiry->email }}</span>
            </div>
        </form>
        <script>
            (function () {
                var form = document.querySelector('form[data-prevent-double-submit]');
                if (!form) {
                    return;
                }
                form.addEventListener('submit', function () {
                    var button = form.querySelector('button[type="submit"]');
                    if (!button || button.disabled) {
                        return;
                    }
                    button.disabled = true;
                    button.textContent = button.dataset.sendingLabel || 'Sending...';
                });
            })();
        </script>
    </x-ui.card>
@endsection
