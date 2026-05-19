@extends('layouts.admin')

@section('title', 'Invoices')
@section('eyebrow', 'Studio')
@section('heading', 'Invoices')
@section('subheading', 'Drafts, sent, and paid. Filter by status to focus on what needs action.')
@section('header_actions')
    <a class="cta" href="{{ route('admin.invoices.create') }}">New Invoice</a>
@endsection
@section('content')
    <section class="admin-card">
        <nav class="admin-tabs">
            <a class="{{ $currentStatus === 'all' ? 'is-active' : '' }}" href="{{ route('admin.invoices.index') }}">All</a>
            @foreach ($statusOptions as $key => $label)
                <a class="{{ $currentStatus === $key ? 'is-active' : '' }}" href="{{ route('admin.invoices.index', ['status' => $key]) }}">{{ $label }}</a>
            @endforeach
        </nav>
    </section>

    <div class="admin-table-wrap">
        <table class="admin-table admin-table--cards admin-table--invoices">
            <thead>
                <tr>
                    <th>Number</th>
                    <th>Client</th>
                    <th>Issued</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th style="text-align:right;">Total</th>
                    <th style="text-align:right;">Balance</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td class="invoices-col--number" data-label="Number"><strong>{{ $invoice->number }}</strong></td>
                        <td class="invoices-col--client" data-label="Client">
                            @if ($invoice->billable instanceof \App\Models\Client)
                                <a href="{{ route('admin.clients.show', $invoice->billable) }}">{{ $invoice->billableName() }}</a>
                            @elseif ($invoice->billable instanceof \App\Models\Venue)
                                <a href="{{ route('admin.venues.edit', $invoice->billable) }}">{{ $invoice->billableName() }}</a>
                                <span class="meta"> · vendor</span>
                            @else
                                <span class="meta">—</span>
                            @endif
                        </td>
                        <td class="invoices-col--issued" data-label="Issued">{{ $invoice->issue_date?->format('M j, Y') ?: '—' }}</td>
                        <td class="invoices-col--due" data-label="Due">{{ $invoice->due_date?->format('M j, Y') ?: '—' }}</td>
                        <td class="invoices-col--status" data-label="Status">{{ $statusOptions[$invoice->status] ?? $invoice->status }}</td>
                        <td class="invoices-col--total" data-label="Total" style="text-align:right;">${{ number_format($invoice->total_cents / 100, 2) }}</td>
                        <td class="invoices-col--balance" data-label="Balance" style="text-align:right;">${{ number_format($invoice->amountDueCents() / 100, 2) }}</td>
                        <td class="invoices-col--open"><a href="{{ route('admin.invoices.show', $invoice) }}">View</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">No invoices match this filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($invoices->hasPages())
        <div class="pagination">
            {{ $invoices->links() }}
        </div>
    @endif
@endsection
