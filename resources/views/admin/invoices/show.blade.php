@extends('layouts.admin')

@section('title', $invoice->number)
@section('eyebrow', 'Studio')
@section('heading', $invoice->number)
@section('subheading', $invoice->client?->displayName().' · '.\App\Models\Invoice::statusOptions()[$invoice->status])
@section('header_actions')
    <a class="cta-secondary" href="{{ route('admin.invoices.pdf', $invoice) }}" target="_blank" rel="noopener">Download PDF</a>
    @if ($invoice->isEditable())
        <a class="cta-secondary" href="{{ route('admin.invoices.edit', $invoice) }}">Edit</a>
    @endif
@endsection
@section('content')
    <section class="admin-card">
        <div class="field-grid">
            <div>
                <p class="eyebrow">Bill to</p>
                <strong>{{ $invoice->client?->displayName() }}</strong><br>
                <span class="meta">{{ $invoice->client?->email }}</span>
                @if ($invoice->client?->company)
                    <br><span class="meta">{{ $invoice->client->company }}</span>
                @endif
            </div>
            <div>
                <p class="eyebrow">Issued</p>
                <strong>{{ $invoice->issue_date?->format('M j, Y') }}</strong>
                @if ($invoice->due_date)
                    <p class="eyebrow" style="margin-top:0.75rem;">Due</p>
                    <strong>{{ $invoice->due_date->format('M j, Y') }}</strong>
                @endif
            </div>
            <div>
                <p class="eyebrow">Total</p>
                <strong>${{ number_format($invoice->total_cents / 100, 2) }}</strong>
                <p class="eyebrow" style="margin-top:0.75rem;">Balance due</p>
                <strong>${{ number_format($invoice->amountDueCents() / 100, 2) }}</strong>
            </div>
        </div>
    </section>

    <section class="admin-card">
        <h3>Line items</h3>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th style="text-align:right;">Qty</th>
                        <th style="text-align:right;">Unit</th>
                        <th style="text-align:right;">Tax</th>
                        <th style="text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->lineItems as $item)
                        <tr>
                            <td>{{ $item->description }}</td>
                            <td style="text-align:right;">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                            <td style="text-align:right;">${{ number_format($item->unit_price_cents / 100, 2) }}</td>
                            <td style="text-align:right;">{{ rtrim(rtrim(number_format($item->tax_rate, 2), '0'), '.') }}%</td>
                            <td style="text-align:right;">${{ number_format($item->total_cents / 100, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align:right;">Subtotal</td>
                        <td style="text-align:right;">${{ number_format($invoice->subtotal_cents / 100, 2) }}</td>
                    </tr>
                    @if ($invoice->discount_cents > 0)
                        <tr>
                            <td colspan="4" style="text-align:right;">Discount</td>
                            <td style="text-align:right;">−${{ number_format($invoice->discount_cents / 100, 2) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td colspan="4" style="text-align:right;">Tax</td>
                        <td style="text-align:right;">${{ number_format($invoice->tax_cents / 100, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align:right;"><strong>Total</strong></td>
                        <td style="text-align:right;"><strong>${{ number_format($invoice->total_cents / 100, 2) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    @if ($invoice->installments->isNotEmpty())
        <section class="admin-card">
            <h3>Installments</h3>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Due</th>
                            <th>Amount</th>
                            <th>Paid</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->installments as $inst)
                            <tr>
                                <td>{{ $inst->label ?: 'Installment '.$inst->sequence }}</td>
                                <td>{{ $inst->due_date?->format('M j, Y') ?: '—' }}</td>
                                <td>${{ number_format($inst->amount_cents / 100, 2) }}</td>
                                <td>${{ number_format($inst->amount_paid_cents / 100, 2) }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $inst->status)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="admin-card">
        <h3>Payments</h3>
        @if ($invoice->payments->isEmpty())
            <p class="meta">No payments recorded yet.</p>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Received</th>
                            <th>Gateway</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->payments as $payment)
                            <tr>
                                <td>{{ $payment->received_at?->format('M j, Y g:i a') ?: $payment->created_at?->format('M j, Y') }}</td>
                                <td>{{ \App\Models\Payment::gatewayOptions()[$payment->gateway] ?? $payment->gateway }}</td>
                                <td>${{ number_format($payment->amount_cents / 100, 2) }}</td>
                                <td>{{ ucfirst($payment->status) }}</td>
                                <td><span class="meta">{{ $payment->gateway_payment_id ?: '—' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($invoice->status !== \App\Models\Invoice::STATUS_VOID && $invoice->amountDueCents() > 0)
            <details style="margin-top:1.5rem;">
                <summary class="cta-secondary" style="cursor:pointer;">Record a manual payment</summary>
                <form method="POST" action="{{ route('admin.invoices.payments.store', $invoice) }}" class="admin-form" style="margin-top:1rem;">
                    @csrf
                    <div class="field-grid">
                        <label>
                            Amount ($)
                            <input type="number" step="0.01" min="0.01" name="amount" value="{{ number_format($invoice->amountDueCents() / 100, 2, '.', '') }}" required>
                        </label>
                        <label>
                            Gateway
                            <select name="gateway" required>
                                @foreach (\App\Models\Payment::gatewayOptions() as $value => $label)
                                    <option value="{{ $value }}" @selected($value === \App\Models\Payment::GATEWAY_MANUAL)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        @if ($invoice->installments->isNotEmpty())
                            <label>
                                Apply to installment
                                <select name="invoice_installment_id">
                                    <option value="">— Unallocated —</option>
                                    @foreach ($invoice->installments as $inst)
                                        <option value="{{ $inst->id }}">
                                            {{ $inst->label ?: 'Installment '.$inst->sequence }}
                                            (${{ number_format($inst->amountDueCents() / 100, 2) }} due)
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <label>
                            Received at
                            <input type="datetime-local" name="received_at" value="{{ now()->format('Y-m-d\TH:i') }}">
                        </label>
                        <label>
                            Reference / transaction ID
                            <input type="text" name="gateway_payment_id" placeholder="Optional">
                        </label>
                    </div>
                    <button class="cta" type="submit">Record Payment</button>
                </form>
            </details>
        @endif
    </section>

    @if ($invoice->notes || $invoice->terms)
        <section class="admin-card">
            @if ($invoice->notes)
                <h3>Notes</h3>
                <p style="white-space:pre-line;">{{ $invoice->notes }}</p>
            @endif
            @if ($invoice->terms)
                <h3>Terms</h3>
                <p style="white-space:pre-line;">{{ $invoice->terms }}</p>
            @endif
        </section>
    @endif

    @if ($invoice->internal_notes)
        <section class="admin-card">
            <h3>Internal notes</h3>
            <p style="white-space:pre-line;">{{ $invoice->internal_notes }}</p>
        </section>
    @endif

    <section class="admin-card">
        <h3>Actions</h3>
        <div class="form-actions" style="flex-wrap:wrap; gap:0.75rem;">
            @if ($invoice->status === \App\Models\Invoice::STATUS_DRAFT)
                <form method="POST" action="{{ route('admin.invoices.send', $invoice) }}">
                    @csrf
                    <button class="cta" type="submit">Mark as Sent</button>
                </form>
            @endif

            @if ($invoice->status !== \App\Models\Invoice::STATUS_VOID)
                <form method="POST" action="{{ route('admin.invoices.void', $invoice) }}" onsubmit="return confirm('Void this invoice? It will no longer be payable.');">
                    @csrf
                    <button class="cta-secondary" type="submit">Void</button>
                </form>
            @endif

            @if ($invoice->isEditable())
                <form method="POST" action="{{ route('admin.invoices.destroy', $invoice) }}" onsubmit="return confirm('Delete this draft invoice? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button class="cta-secondary" type="submit" style="color:#a03030;">Delete Draft</button>
                </form>
            @endif
        </div>
    </section>
@endsection
