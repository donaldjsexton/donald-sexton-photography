@extends('layouts.admin')

@php
    use App\Models\Invoice;

    $totalBilledCents = $client->invoices->sum('total_cents');
    $totalPaidCents = $client->invoices->sum('amount_paid_cents');
    $totalOutstandingCents = $client->invoices->sum(fn (Invoice $i) => $i->amountDueCents());

    $money = fn (int $cents) => '$'.number_format($cents / 100, 0);

    $initials = strtoupper(mb_substr($client->first_name ?: '?', 0, 1).mb_substr($client->last_name ?: ($client->partner_first_name ?: ''), 0, 1));

    $sinceDate = $client->inquiries->min('created_at') ?? $client->created_at;

    $bookings = $client->inquiries
        ->filter(fn ($inquiry) => $inquiry->bookedJob !== null)
        ->sortByDesc(fn ($inquiry) => $inquiry->bookedJob->event_date ?? $inquiry->created_at);

    $location = trim(($client->city ?: '').($client->state ? ', '.$client->state : ''), ', ');
@endphp

@section('title', $client->displayName())
@section('eyebrow', 'Studio')
@section('heading', $client->displayName())
@section('content')
    @if (session('error'))
        <div class="admin-flash admin-flash--error" style="margin-bottom:1rem;">{{ session('error') }}</div>
    @endif

    <div class="client-profile">
        <section class="client-hero">
            <div class="client-hero__avatar" aria-hidden="true">{{ $initials }}</div>
            <h2 class="client-hero__name">{{ $client->displayName() }}</h2>
            @if ($client->company)
                <p class="client-hero__tagline">{{ $client->company }}</p>
            @endif
            <p class="client-hero__since">Client since {{ optional($sinceDate)->format('M Y') }}</p>

            <div class="client-hero__chips">
                <a class="client-chip" href="mailto:{{ $client->email }}">✉️ {{ $client->email }}</a>
                @if ($client->phone)
                    <a class="client-chip" href="tel:{{ preg_replace('/[^0-9+]/', '', $client->phone) }}">📞 {{ $client->phone }}</a>
                @endif
                @if ($location)
                    <span class="client-chip">📍 {{ $location }}</span>
                @endif
            </div>

            <div class="client-stats">
                <div class="client-stat">
                    <span class="client-stat__value">{{ $money($totalBilledCents) }}</span>
                    <span class="client-stat__label">Billed</span>
                </div>
                <div class="client-stat">
                    <span class="client-stat__value">{{ $money($totalPaidCents) }}</span>
                    <span class="client-stat__label">Paid</span>
                </div>
                <div class="client-stat {{ $totalOutstandingCents > 0 ? 'client-stat--alert' : '' }}">
                    <span class="client-stat__value">{{ $money($totalOutstandingCents) }}</span>
                    <span class="client-stat__label">Due</span>
                </div>
            </div>

            <div class="client-hero__actions">
                <a class="cta client-cta--primary" href="{{ route('admin.proposals.create', ['client_id' => $client->id]) }}">＋ New Proposal</a>
                <div class="client-hero__actions-row">
                    <a class="cta-secondary" href="{{ route('admin.contracts.create', ['client_id' => $client->id]) }}">Contract</a>
                    <a class="cta-secondary" href="{{ route('admin.invoices.create', ['client_id' => $client->id]) }}">Invoice</a>
                    <a class="cta-secondary" href="{{ route('admin.clients.edit', $client) }}">Edit</a>
                </div>
            </div>
        </section>

        @if ($bookings->isNotEmpty())
            <section class="client-section">
                <h3 class="client-section__title">Bookings</h3>
                <div class="client-bookings">
                    @foreach ($bookings as $inquiry)
                        @php($job = $inquiry->bookedJob)
                        <article class="client-booking">
                            <div class="client-booking__head">
                                <div>
                                    <strong class="client-booking__name">{{ $job->summary ?: $job->couple_names ?: 'Booking' }}</strong>
                                    <span class="client-booking__date">
                                        @if ($job->event_date){{ $job->event_date->format('l, M j, Y') }}@else Date TBD @endif
                                    </span>
                                </div>
                                <span class="client-pill client-pill--{{ $job->isCancelled() ? 'muted' : 'live' }}">{{ $job->portalStage() }}</span>
                            </div>
                            <div class="client-booking__actions">
                                <a href="{{ route('admin.proposals.create', ['client_id' => $client->id, 'booked_job_id' => $job->id]) }}">＋ Proposal</a>
                                <a href="{{ route('admin.contracts.create', ['client_id' => $client->id, 'booked_job_id' => $job->id]) }}">＋ Contract</a>
                                <a href="{{ route('admin.invoices.create', ['client_id' => $client->id, 'booked_job_id' => $job->id]) }}">＋ Invoice</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="client-section">
            <h3 class="client-section__title">Activity</h3>
            @if (empty($timeline))
                <p class="client-empty">Nothing yet. New inquiries from {{ $client->email }} land here automatically.</p>
            @else
                <ol class="client-feed">
                    @foreach ($timeline as $event)
                        <li class="client-feed__item client-feed__item--{{ $event['kind'] }}">
                            @php($tag = $event['url'] ? 'a' : 'div')
                            <{{ $tag }} class="client-feed__link" @if ($event['url']) href="{{ $event['url'] }}" @endif>
                                <span class="client-feed__icon" aria-hidden="true">{{ $event['icon'] }}</span>
                                <span class="client-feed__body">
                                    <span class="client-feed__title">{{ $event['title'] }}</span>
                                    @if ($event['meta'])
                                        <span class="client-feed__meta">{{ $event['meta'] }}</span>
                                    @endif
                                </span>
                                <time class="client-feed__time" datetime="{{ optional($event['at'])->toIso8601String() }}">
                                    {{ optional($event['at'])->diffForHumans(null, true) }} ago
                                </time>
                            </{{ $tag }}>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <section class="client-section">
            <div class="client-aux">
                <div class="client-aux__item">
                    <h3 class="client-section__title">Portal access</h3>
                    @if ($client->password !== null)
                        <p class="client-muted">
                            Active · {{ $client->last_login_at ? 'last sign-in '.$client->last_login_at->diffForHumans() : 'not signed in yet' }}
                            @if ($loginCount > 0)
                                · {{ $loginCount }} {{ Str::plural('sign-in', $loginCount) }}
                            @endif
                        </p>
                    @else
                        <p class="client-muted">No portal access yet.</p>
                        <form method="POST" action="{{ route('admin.clients.portal-invite', $client) }}">
                            @csrf
                            <button class="cta-secondary" type="submit">Send portal invite</button>
                        </form>
                    @endif
                </div>

                @if ($client->notes)
                    <details class="client-aux__item">
                        <summary class="client-section__title">Internal notes</summary>
                        <p class="client-notes">{{ $client->notes }}</p>
                    </details>
                @endif
            </div>
        </section>
    </div>
@endsection
