@extends('portal.layouts.app')

@section('title', 'Invoices')

@section('content')
    <section class="card">
        <h2>Invoices</h2>

        @if ($invoices->isEmpty())
            <p class="meta" style="margin:0;">You don&rsquo;t have any invoices yet.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Issued</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th class="num">Total</th>
                        <th class="num">Balance</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr>
                            <td><strong>{{ $invoice->number }}</strong></td>
                            <td>{{ $invoice->issue_date?->format('M j, Y') ?: '—' }}</td>
                            <td>{{ $invoice->due_date?->format('M j, Y') ?: '—' }}</td>
                            <td><span class="pill">{{ \App\Models\Invoice::statusOptions()[$invoice->status] ?? $invoice->status }}</span></td>
                            <td class="num">${{ number_format($invoice->total_cents / 100, 2) }}</td>
                            <td class="num">${{ number_format($invoice->amountDueCents() / 100, 2) }}</td>
                            <td><a href="{{ route('portal.invoices.show', ['invoice' => $invoice->uuid]) }}">View</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($invoices->hasPages())
                <div style="margin-top:24px;">
                    {{ $invoices->links() }}
                </div>
            @endif
        @endif
    </section>
@endsection
