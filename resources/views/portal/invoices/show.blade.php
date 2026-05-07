@extends('portal.layouts.app')

@section('title', 'Invoice '.$invoice->number)

@section('content')
    <section class="card stack">
        <div style="display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:16px;">
            <div>
                <h3>Invoice</h3>
                <h2 style="margin:0;">{{ $invoice->number }}</h2>
                <p class="meta" style="margin:6px 0 0;">
                    Issued {{ $invoice->issue_date?->format('M j, Y') }}
                    @if ($invoice->due_date)
                        · Due {{ $invoice->due_date->format('M j, Y') }}
                    @endif
                </p>
                <span class="pill" style="margin-top:8px;">{{ \App\Models\Invoice::statusOptions()[$invoice->status] ?? $invoice->status }}</span>
            </div>
            <div style="text-align:right;">
                <h3>Balance due</h3>
                <p style="margin:0; font-size:24px; font-weight:600;">${{ number_format($invoice->amountDueCents() / 100, 2) }}</p>
                <p class="meta" style="margin:4px 0 0;">of ${{ number_format($invoice->total_cents / 100, 2) }} total</p>
            </div>
        </div>

        <div>
            <h3>Line items</h3>
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="num">Qty</th>
                        <th class="num">Unit</th>
                        <th class="num">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->lineItems as $item)
                        <tr>
                            <td>{{ $item->description }}</td>
                            <td class="num">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                            <td class="num">${{ number_format($item->unit_price_cents / 100, 2) }}</td>
                            <td class="num">${{ number_format($item->total_cents / 100, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><td colspan="3" class="num">Subtotal</td><td class="num">${{ number_format($invoice->subtotal_cents / 100, 2) }}</td></tr>
                    @if ($invoice->discount_cents > 0)
                        <tr><td colspan="3" class="num">Discount</td><td class="num">−${{ number_format($invoice->discount_cents / 100, 2) }}</td></tr>
                    @endif
                    <tr><td colspan="3" class="num">Tax</td><td class="num">${{ number_format($invoice->tax_cents / 100, 2) }}</td></tr>
                    <tr><td colspan="3" class="num"><strong>Total</strong></td><td class="num"><strong>${{ number_format($invoice->total_cents / 100, 2) }}</strong></td></tr>
                    @if ($invoice->amount_paid_cents > 0)
                        <tr><td colspan="3" class="num">Paid</td><td class="num">−${{ number_format($invoice->amount_paid_cents / 100, 2) }}</td></tr>
                    @endif
                </tfoot>
            </table>
        </div>

        @if ($invoice->installments->isNotEmpty())
            <div>
                <h3>Payment schedule</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Due</th>
                            <th class="num">Amount</th>
                            <th class="num">Paid</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->installments as $inst)
                            <tr>
                                <td>{{ $inst->label ?: 'Installment '.$inst->sequence }}</td>
                                <td>{{ $inst->due_date?->format('M j, Y') ?: '—' }}</td>
                                <td class="num">${{ number_format($inst->amount_cents / 100, 2) }}</td>
                                <td class="num">${{ number_format($inst->amount_paid_cents / 100, 2) }}</td>
                                <td><span class="pill">{{ ucfirst(str_replace('_', ' ', $inst->status)) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($invoice->payments->isNotEmpty())
            <div>
                <h3>Payment history</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Received</th>
                            <th>Method</th>
                            <th class="num">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->payments->where('status', \App\Models\Payment::STATUS_COMPLETED) as $payment)
                            <tr>
                                <td>{{ $payment->received_at?->format('M j, Y') ?: $payment->created_at?->format('M j, Y') }}</td>
                                <td>{{ \App\Models\Payment::gatewayOptions()[$payment->gateway] ?? $payment->gateway }}</td>
                                <td class="num">${{ number_format($payment->amount_cents / 100, 2) }}</td>
                                <td><span class="pill">{{ ucfirst($payment->status) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($invoice->notes)
            <div>
                <h3>Notes</h3>
                <p style="margin:0; white-space:pre-line;">{{ $invoice->notes }}</p>
            </div>
        @endif

        @if ($invoice->terms)
            <div>
                <h3>Terms</h3>
                <p style="margin:0; white-space:pre-line;">{{ $invoice->terms }}</p>
            </div>
        @endif

        <div style="display:flex; flex-wrap:wrap; gap:12px;">
            <a class="btn btn-secondary" href="{{ route('portal.invoices.pdf', ['invoice' => $invoice->uuid]) }}" target="_blank" rel="noopener">Download PDF</a>
            @if ($invoice->amountDueCents() > 0 && $invoice->status !== \App\Models\Invoice::STATUS_VOID)
                <span class="btn btn-primary" aria-disabled="true" title="Online payment options are coming soon">Pay Online (coming soon)</span>
            @endif
        </div>
    </section>
@endsection
