<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->number }} — {{ config('payments.business.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
    <style>
        :root { color-scheme: light; }
        body { margin: 0; font-family: 'Helvetica Neue', Arial, sans-serif; color: #2d1d15; background: #f6efe6; }
        .invoice-shell { max-width: 820px; margin: 0 auto; padding: 32px 20px; }
        .invoice-card { background: #fff; border: 1px solid #e7d8c5; border-radius: 12px; padding: 32px; }
        .invoice-card__header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 24px; padding-bottom: 18px; border-bottom: 1px solid #e7d8c5; }
        .invoice-card__brand h1 { margin: 0; font-size: 22px; letter-spacing: 0.04em; }
        .meta { color: #6b5446; font-size: 13px; line-height: 1.5; }
        .invoice-card__meta { text-align: right; }
        .invoice-card__meta strong { display: block; font-size: 22px; }
        .pill { display: inline-block; padding: 4px 12px; border-radius: 999px; background: #f1e7da; color: #6b5446; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; margin-top: 8px; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 16px; }
        .btn { display: inline-block; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; min-height: 44px; line-height: 24px; }
        .btn-primary { background: #2d1d15; color: #fff; }
        .btn-secondary { background: transparent; color: #2d1d15; border: 1px solid #2d1d15; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 14px; }
        th { text-align: left; padding: 10px 8px; border-bottom: 1px solid #e7d8c5; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #6b5446; }
        td { padding: 12px 8px; border-bottom: 1px solid #f6efe6; }
        td.num, th.num { text-align: right; }
        .totals { margin-left: auto; max-width: 320px; }
        .totals td { padding: 6px 8px; }
        .totals tr.total td { border-top: 2px solid #2d1d15; font-weight: 700; font-size: 16px; padding-top: 10px; }
        .stack > * + * { margin-top: 24px; }
        h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #6b5446; margin: 0 0 8px; }
        @media (max-width: 600px) {
            .invoice-card { padding: 20px; border-radius: 8px; }
            .invoice-card__header { flex-direction: column; align-items: flex-start; }
            .invoice-card__meta { text-align: left; }
            table { font-size: 13px; }
            .totals { max-width: none; }
        }
    </style>
</head>
<body>
    <div class="invoice-shell">
        <div class="invoice-card stack">
            <div class="invoice-card__header">
                <div class="invoice-card__brand">
                    <h1>{{ config('payments.business.name') }}</h1>
                    <p class="meta">
                        @if ($email = config('payments.business.email')){{ $email }}<br>@endif
                        @if ($phone = config('payments.business.phone')){{ $phone }}@endif
                    </p>
                </div>
                <div class="invoice-card__meta">
                    <strong>{{ $invoice->number }}</strong>
                    <span class="meta">Issued {{ $invoice->issue_date?->format('M j, Y') }}</span><br>
                    @if ($invoice->due_date)
                        <span class="meta">Due {{ $invoice->due_date->format('M j, Y') }}</span>
                    @endif
                    <div class="pill">{{ \App\Models\Invoice::statusOptions()[$invoice->status] ?? $invoice->status }}</div>
                </div>
            </div>

            <div>
                <h3>Bill to</h3>
                <strong>{{ $invoice->client?->displayName() }}</strong><br>
                <span class="meta">{{ $invoice->client?->email }}</span>
                @if ($invoice->client?->company)
                    <br><span class="meta">{{ $invoice->client->company }}</span>
                @endif
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
                </table>

                <table class="totals">
                    <tr><td>Subtotal</td><td class="num">${{ number_format($invoice->subtotal_cents / 100, 2) }}</td></tr>
                    @if ($invoice->discount_cents > 0)
                        <tr><td>Discount</td><td class="num">−${{ number_format($invoice->discount_cents / 100, 2) }}</td></tr>
                    @endif
                    <tr><td>Tax</td><td class="num">${{ number_format($invoice->tax_cents / 100, 2) }}</td></tr>
                    <tr class="total"><td>Total</td><td class="num">${{ number_format($invoice->total_cents / 100, 2) }}</td></tr>
                    @if ($invoice->amount_paid_cents > 0)
                        <tr><td>Paid</td><td class="num">−${{ number_format($invoice->amount_paid_cents / 100, 2) }}</td></tr>
                        <tr class="total"><td>Balance due</td><td class="num">${{ number_format($invoice->amountDueCents() / 100, 2) }}</td></tr>
                    @endif
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
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->installments as $inst)
                                <tr>
                                    <td>{{ $inst->label ?: 'Installment '.$inst->sequence }}</td>
                                    <td>{{ $inst->due_date?->format('M j, Y') ?: '—' }}</td>
                                    <td class="num">${{ number_format($inst->amount_cents / 100, 2) }}</td>
                                    <td class="num">${{ number_format($inst->amount_paid_cents / 100, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($invoice->notes)
                <div>
                    <h3>Notes</h3>
                    <p style="white-space:pre-line; margin:0;">{{ $invoice->notes }}</p>
                </div>
            @endif

            @if ($invoice->terms)
                <div>
                    <h3>Terms</h3>
                    <p style="white-space:pre-line; margin:0;">{{ $invoice->terms }}</p>
                </div>
            @endif

            <div class="actions">
                <a class="btn btn-secondary" href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('invoices.public.pdf', now()->addDays((int) config('payments.invoice_signed_url_ttl_days', 90)), ['invoice' => $invoice->uuid]) }}">Download PDF</a>
                <a class="btn btn-secondary" href="{{ route('portal.login') }}">Sign in to your portal</a>
                @if ($invoice->amountDueCents() > 0 && $invoice->status !== \App\Models\Invoice::STATUS_VOID)
                    <span class="btn btn-primary" aria-disabled="true" title="Online payment options are coming soon">Pay Online (coming soon)</span>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
