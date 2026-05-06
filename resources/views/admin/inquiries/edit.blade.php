@extends('layouts.admin')

@section('title', 'Inquiry')
@section('eyebrow', 'Leads')
@section('heading', $inquiry->primary_name)
@section('subheading', 'Review inquiry details and move this lead through the studio pipeline.')
@section('header_actions')
    <a class="cta-secondary" href="{{ route('admin.inquiries.index') }}">Back to Inquiries</a>
@endsection
@section('content')
    @if (session('error'))
        <div class="admin-flash admin-flash--error" style="margin-bottom:1.5rem;">{{ session('error') }}</div>
    @endif

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

            <form method="POST" action="{{ route('admin.inquiries.destroy', $inquiry) }}" class="admin-form" style="margin-top:1.5rem; border-top:1px solid #efe3d7; padding-top:1.5rem;" onsubmit="return confirm('Delete this inquiry permanently? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <p class="meta">Delete this lead if it arrived as spam or in error. This removes the inquiry and all its messages.</p>
                <button class="cta-secondary" type="submit" style="border: 0; cursor: pointer; color:#a03030;">Delete Inquiry</button>
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
                @if ($inquiry->first_responded_at)
                    <div class="admin-detail-list__item">
                        <strong>First response</strong>
                        <span class="meta">{{ $inquiry->first_responded_at->format('F j, Y g:i A') }} ({{ $inquiry->first_responded_at->diffForHumans($inquiry->created_at, \Carbon\CarbonInterface::DIFF_ABSOLUTE) }})</span>
                    </div>
                @endif
            </div>
        </article>
    </section>

    <section class="admin-card">
        <p class="eyebrow">Wedding Questionnaire</p>

        @if ($inquiry->questionnaire)
            @php $q = $inquiry->questionnaire; @endphp
            <div class="admin-detail-list">
                <div class="admin-detail-list__item">
                    <strong>Status</strong>
                    <span class="meta">{{ $q->isSubmitted() ? 'Submitted '.$q->submitted_at->format('F j, Y g:i A') : 'Link generated — awaiting response' }}</span>
                </div>
                <div class="admin-detail-list__item">
                    <strong>Shareable link</strong>
                    <span class="meta" style="word-break:break-all;"><a href="{{ $q->publicUrl() }}" target="_blank" rel="noopener">{{ $q->publicUrl() }}</a></span>
                </div>
            </div>

            @if ($q->isSubmitted())
                <div style="margin-top:1rem;">
                    <a class="cta" href="{{ route('admin.inquiries.questionnaire.show', $inquiry) }}">View Responses</a>
                </div>
            @endif
        @else
            <p class="meta">No questionnaire has been sent yet. Generate a link to share with the couple.</p>
            <form method="POST" action="{{ route('admin.inquiries.questionnaire.generate', $inquiry) }}" class="admin-form" style="margin-top:1rem;">
                @csrf
                <button class="cta" type="submit" style="border:0; cursor:pointer;">Generate Questionnaire Link</button>
            </form>
        @endif
    </section>

    <section class="admin-card">
        <p class="eyebrow">Conversations</p>

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

        <div class="inquiry-threads">
            @foreach ($threadGroups as $thread)
                <article class="inquiry-thread">
                    <header class="inquiry-thread__header">
                        <strong class="inquiry-thread__subject">{{ $thread['subject'] }}</strong>
                        <span class="meta">
                            {{ $thread['messages']->count() }} {{ Str::plural('message', $thread['messages']->count()) }}
                            · last activity {{ optional($thread['last_at'])->format('M j, Y g:i A') }}
                        </span>
                    </header>

                    <div class="inquiry-timeline">
                        @foreach ($thread['messages'] as $msg)
                            @php $stamp = $msg->sent_at ?? $msg->created_at; @endphp
                            <div class="inquiry-timeline__entry inquiry-timeline__entry--{{ $msg->direction }}">
                                <div class="inquiry-timeline__meta">
                                    <strong>{{ $msg->direction === 'outbound' ? ($msg->sender_name ?: 'Studio') : ($msg->sender_name ?: $inquiry->primary_name) }}</strong>
                                    <span class="meta">{{ optional($stamp)->format('M j, Y g:i A') }}</span>
                                </div>
                                <div class="inquiry-timeline__body">{{ $msg->body }}</div>
                            </div>
                        @endforeach
                    </div>
                </article>
            @endforeach

            <article class="inquiry-thread inquiry-thread--initial">
                <header class="inquiry-thread__header">
                    <strong class="inquiry-thread__subject">Initial inquiry</strong>
                    <span class="meta">submitted {{ optional($initialAt)->format('M j, Y g:i A') }}</span>
                </header>
                <div class="inquiry-timeline">
                    <div class="inquiry-timeline__entry inquiry-timeline__entry--inbound">
                        <div class="inquiry-timeline__meta">
                            <strong>{{ $inquiry->primary_name }}</strong>
                            <span class="meta">{{ optional($initialAt)->format('M j, Y g:i A') }} · form submission</span>
                        </div>
                        <div class="inquiry-timeline__body">{{ $inquiry->message ?: 'No message was included.' }}</div>
                    </div>
                </div>
            </article>
        </div>

        <form
            method="POST"
            action="{{ route('admin.inquiries.reply', $inquiry) }}"
            class="admin-form inquiry-reply-form"
            data-prevent-double-submit
        >
            @csrf
            <label>
                Reply to {{ $inquiry->primary_name }}
                <textarea name="body" rows="4" required placeholder="Write your reply...">{{ old('body') }}</textarea>
            </label>
            @error('body')
                <p class="admin-form__error">{{ $message }}</p>
            @enderror
            <div class="inquiry-reply-form__actions">
                <button
                    class="cta"
                    type="submit"
                    style="border: 0; cursor: pointer;"
                    data-sending-label="Sending..."
                >Send Reply</button>
                <span class="meta">Sends to {{ $inquiry->email }}</span>
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
    </section>
@endsection
