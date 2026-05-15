@extends('layouts.admin')

@php
    use App\Models\Contract;
    use App\Models\Invoice;

    $invoiceStatusOptions = Invoice::statusOptions();
    $contractStatusOptions = Contract::statusOptions();

    $totalBilledCents = $client->invoices->sum('total_cents');
    $totalPaidCents = $client->invoices->sum('amount_paid_cents');
    $totalOutstandingCents = $client->invoices->sum(fn (Invoice $i) => $i->amountDueCents());

    $contractsByBookedJob = $client->contracts->groupBy(fn (Contract $c) => $c->booked_job_id);
    $invoicesByBookedJob = $client->invoices->groupBy(fn (Invoice $i) => $i->booked_job_id);

    $standaloneContracts = $contractsByBookedJob->get(null, collect());
    $standaloneInvoices = $invoicesByBookedJob->get(null, collect());

    $money = fn (int $cents) => '$'.number_format($cents / 100, 2);
@endphp

@section('title', $client->displayName())
@section('eyebrow', 'Studio')
@section('heading', $client->displayName())
@section('subheading', $client->email)
@section('header_actions')
    <a class="cta" href="{{ route('admin.contracts.create', ['client_id' => $client->id]) }}">New Contract</a>
    <a class="cta" href="{{ route('admin.invoices.create', ['client_id' => $client->id]) }}">New Invoice</a>
    <a class="cta-secondary" href="{{ route('admin.clients.edit', $client) }}">Edit</a>
@endsection
@section('content')
    @if (session('error'))
        <div class="admin-flash admin-flash--error" style="margin-bottom:1.5rem;">{{ session('error') }}</div>
    @endif

    <section class="admin-card">
        <h3>Lifetime billing</h3>
        <div class="admin-stat-strip" style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem;">
            <div>
                <div class="meta">Billed</div>
                <div style="font-size:1.5rem; font-weight:600;">{{ $money($totalBilledCents) }}</div>
            </div>
            <div>
                <div class="meta">Paid</div>
                <div style="font-size:1.5rem; font-weight:600;">{{ $money($totalPaidCents) }}</div>
            </div>
            <div>
                <div class="meta">Outstanding</div>
                <div style="font-size:1.5rem; font-weight:600;">{{ $money($totalOutstandingCents) }}</div>
            </div>
        </div>
    </section>

    <section class="admin-card">
        <h3>Contact</h3>
        <dl class="admin-detail-list">
            <dt>Email</dt>
            <dd>{{ $client->email }}</dd>

            <dt>Phone</dt>
            <dd>{{ $client->phone ?: '—' }}</dd>

            <dt>Company</dt>
            <dd>{{ $client->company ?: '—' }}</dd>

            <dt>Address</dt>
            <dd>
                @php
                    $address = collect([
                        $client->address_line_1,
                        $client->address_line_2,
                        trim(($client->city ? $client->city : '').($client->state ? ', '.$client->state : '')),
                        $client->postal_code,
                        $client->country,
                    ])->filter()->implode(' · ');
                @endphp
                {{ $address ?: '—' }}
            </dd>
        </dl>
    </section>

    <section class="admin-card">
        <h3>Portal Access</h3>
        @if ($client->password !== null)
            <p class="meta" style="margin:0;">
                Client has set up their portal
                @if ($client->last_login_at)
                    · last signed in {{ $client->last_login_at->diffForHumans() }}
                @else
                    · has not signed in yet
                @endif.
            </p>
        @else
            <p class="meta" style="margin:0 0 12px;">No portal password set yet. Send an invitation email with a magic link to let {{ $client->portalGreeting() }} pick a password.</p>
            <form method="POST" action="{{ route('admin.clients.portal-invite', $client) }}" class="admin-form">
                @csrf
                <button class="cta" type="submit" style="border:0; cursor:pointer;">Send Portal Invite</button>
            </form>
        @endif
    </section>

    @if ($client->notes)
        <section class="admin-card">
            <h3>Internal notes</h3>
            <p style="white-space: pre-line;">{{ $client->notes }}</p>
        </section>
    @endif

    <section class="admin-card">
        <h3>Events</h3>
        @if ($client->inquiries->isEmpty())
            <p class="meta">No inquiries yet. New inquiries from {{ $client->email }} will attach to this client automatically.</p>
        @else
            @foreach ($client->inquiries as $inquiry)
                @php
                    $bookedJob = $inquiry->bookedJob;
                    $eventContracts = $bookedJob
                        ? $contractsByBookedJob->get($bookedJob->id, collect())
                        : collect();
                    $eventInvoices = $bookedJob
                        ? $invoicesByBookedJob->get($bookedJob->id, collect())
                        : collect();
                @endphp

                <article class="admin-subcard" style="border:1px solid var(--admin-border, #d8d4c8); border-radius:8px; padding:1rem; margin-bottom:1rem;">
                    <header style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:0.5rem; align-items:baseline;">
                        <div>
                            <strong>
                                <a href="{{ route('admin.inquiries.edit', $inquiry) }}">Inquiry #{{ $inquiry->id }}</a>
                            </strong>
                            <span class="meta">
                                · {{ \App\Models\Inquiry::statusOptions()[$inquiry->status] ?? $inquiry->status }}
                                @if ($inquiry->event_date)
                                    · {{ $inquiry->event_date->format('M j, Y') }}
                                @endif
                                @if ($inquiry->venue_name)
                                    · {{ $inquiry->venue_name }}
                                @endif
                            </span>
                        </div>
                        <div>
                            @if ($bookedJob)
                                <a class="cta-secondary" href="{{ route('admin.contracts.create', ['client_id' => $client->id, 'booked_job_id' => $bookedJob->id]) }}">+ Contract</a>
                                <a class="cta-secondary" href="{{ route('admin.invoices.create', ['client_id' => $client->id, 'booked_job_id' => $bookedJob->id]) }}">+ Invoice</a>
                            @endif
                        </div>
                    </header>

                    @if ($bookedJob)
                        <p class="meta" style="margin:0.5rem 0;">
                            <strong>Booked job:</strong>
                            {{ $bookedJob->summary ?: $bookedJob->couple_names ?: 'Confirmed' }}
                            @if ($bookedJob->event_date)
                                · {{ $bookedJob->event_date->format('M j, Y') }}
                            @endif
                            · {{ $bookedJob->portalStage() }}
                        </p>
                    @else
                        <p class="meta" style="margin:0.5rem 0;">No booked job yet — mark this inquiry as booked to confirm.</p>
                    @endif

                    @if ($eventContracts->isNotEmpty())
                        <div class="admin-table-wrap" style="margin-top:0.75rem;">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Contract</th>
                                        <th>Issued</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($eventContracts as $contract)
                                        <tr>
                                            <td><a href="{{ route('admin.contracts.show', $contract) }}">{{ $contract->number }}</a></td>
                                            <td>{{ $contract->issue_date?->format('M j, Y') ?: '—' }}</td>
                                            <td>{{ $contractStatusOptions[$contract->status] ?? $contract->status }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if ($eventInvoices->isNotEmpty())
                        <div class="admin-table-wrap" style="margin-top:0.75rem;">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Issued</th>
                                        <th>Status</th>
                                        <th>Total</th>
                                        <th>Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($eventInvoices as $invoice)
                                        <tr>
                                            <td><a href="{{ route('admin.invoices.show', $invoice) }}">{{ $invoice->number }}</a></td>
                                            <td>{{ $invoice->issue_date?->format('M j, Y') ?: '—' }}</td>
                                            <td>{{ $invoiceStatusOptions[$invoice->status] ?? $invoice->status }}</td>
                                            <td>{{ $money($invoice->total_cents) }}</td>
                                            <td>{{ $money($invoice->amountDueCents()) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </article>
            @endforeach
        @endif
    </section>

    @if ($standaloneContracts->isNotEmpty() || $standaloneInvoices->isNotEmpty())
        <section class="admin-card">
            <h3>Other billing (not tied to an event)</h3>

            @if ($standaloneContracts->isNotEmpty())
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Contract</th>
                                <th>Issued</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($standaloneContracts as $contract)
                                <tr>
                                    <td><a href="{{ route('admin.contracts.show', $contract) }}">{{ $contract->number }}</a></td>
                                    <td>{{ $contract->issue_date?->format('M j, Y') ?: '—' }}</td>
                                    <td>{{ $contractStatusOptions[$contract->status] ?? $contract->status }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($standaloneInvoices->isNotEmpty())
                <div class="admin-table-wrap" style="margin-top:0.75rem;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Issued</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($standaloneInvoices as $invoice)
                                <tr>
                                    <td><a href="{{ route('admin.invoices.show', $invoice) }}">{{ $invoice->number }}</a></td>
                                    <td>{{ $invoice->issue_date?->format('M j, Y') ?: '—' }}</td>
                                    <td>{{ $invoiceStatusOptions[$invoice->status] ?? $invoice->status }}</td>
                                    <td>{{ $money($invoice->total_cents) }}</td>
                                    <td>{{ $money($invoice->amountDueCents()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
@endsection
