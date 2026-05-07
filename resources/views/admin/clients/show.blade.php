@extends('layouts.admin')

@section('title', $client->displayName())
@section('eyebrow', 'Studio')
@section('heading', $client->displayName())
@section('subheading', $client->email)
@section('header_actions')
    <a class="cta" href="{{ route('admin.invoices.create', ['client_id' => $client->id]) }}">New Invoice</a>
    <a class="cta-secondary" href="{{ route('admin.clients.edit', $client) }}">Edit</a>
@endsection
@section('content')
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

            @if ($client->inquiry)
                <dt>Inquiry</dt>
                <dd>
                    <a href="{{ route('admin.inquiries.edit', $client->inquiry) }}">
                        #{{ $client->inquiry->id }} — {{ $client->inquiry->primary_name }}
                    </a>
                </dd>
            @endif
        </dl>
    </section>

    @if ($client->notes)
        <section class="admin-card">
            <h3>Internal notes</h3>
            <p style="white-space: pre-line;">{{ $client->notes }}</p>
        </section>
    @endif

    <section class="admin-card">
        <h3>Invoices</h3>
        @if ($client->invoices->isEmpty())
            <p class="meta">No invoices yet.</p>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Issued</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($client->invoices as $invoice)
                            <tr>
                                <td><a href="{{ route('admin.invoices.show', $invoice) }}">{{ $invoice->number }}</a></td>
                                <td>{{ $invoice->issue_date?->format('M j, Y') ?: '—' }}</td>
                                <td>{{ \App\Models\Invoice::statusOptions()[$invoice->status] ?? $invoice->status }}</td>
                                <td>${{ number_format($invoice->total_cents / 100, 2) }}</td>
                                <td>${{ number_format($invoice->amountDueCents() / 100, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
