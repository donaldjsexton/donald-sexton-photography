@extends('portal.layouts.app')

@section('title', 'Overview')

@section('content')
    <section class="card stack">
        <div>
            <h2>Hi, {{ $billable->portalGreeting() }}.</h2>
            <p class="meta" style="margin:0;">Here&rsquo;s a quick look at your account.</p>
        </div>

        <div class="grid-3">
            <div class="stat">
                <span class="label">Outstanding balance</span>
                <span class="value">${{ number_format($outstandingCents / 100, 2) }}</span>
            </div>
            <div class="stat">
                <span class="label">Next payment due</span>
                @if ($nextInstallment)
                    <span class="value">${{ number_format($nextInstallment->amount_cents / 100, 2) }}</span>
                    <span class="meta">{{ $nextInstallment->due_date->format('M j, Y') }} · {{ $nextInstallment->label ?: 'Installment '.$nextInstallment->sequence }}</span>
                @else
                    <span class="value">—</span>
                    <span class="meta">No upcoming installments</span>
                @endif
            </div>
            <div class="stat">
                <span class="label">Open invoices</span>
                <span class="value">{{ $invoices->whereIn('status', [\App\Models\Invoice::STATUS_SENT, \App\Models\Invoice::STATUS_PARTIALLY_PAID, \App\Models\Invoice::STATUS_OVERDUE])->count() }}</span>
            </div>
        </div>
    </section>

    @if ($upcomingBooking)
        <section class="card">
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:8px;">
                <h3 style="margin:0;">Your booking</h3>
                <span class="pill">{{ $upcomingBooking->portalStage() }}</span>
            </div>
            <p style="margin:0;">
                <strong>{{ $upcomingBooking->summary }}</strong><br>
                <span class="meta">{{ $upcomingBooking->event_date->format('l, F j, Y') }} @if ($upcomingBooking->event_time) at {{ $upcomingBooking->event_time }} @endif</span>
                @if ($upcomingBooking->location)
                    <br><span class="meta">{{ $upcomingBooking->location }}</span>
                @endif
            </p>
        </section>
    @endif

    <section class="card">
        <h3>Recent invoices</h3>
        @if ($invoices->isEmpty())
            <p class="meta" style="margin:0;">You don&rsquo;t have any invoices yet.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Issued</th>
                        <th>Status</th>
                        <th class="num">Total</th>
                        <th class="num">Balance</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices->take(8) as $invoice)
                        <tr>
                            <td data-label="Number"><strong>{{ $invoice->number }}</strong></td>
                            <td data-label="Issued">{{ $invoice->issue_date?->format('M j, Y') }}</td>
                            <td data-label="Status"><span class="pill">{{ \App\Models\Invoice::statusOptions()[$invoice->status] ?? $invoice->status }}</span></td>
                            <td class="num" data-label="Total">${{ number_format($invoice->total_cents / 100, 2) }}</td>
                            <td class="num" data-label="Balance">${{ number_format($invoice->amountDueCents() / 100, 2) }}</td>
                            <td><a href="{{ route('portal.invoices.show', ['invoice' => $invoice->uuid]) }}">View</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($invoices->count() > 8)
                <p style="margin:18px 0 0;"><a href="{{ route('portal.invoices.index') }}">See all invoices →</a></p>
            @endif
        @endif
    </section>
@endsection
