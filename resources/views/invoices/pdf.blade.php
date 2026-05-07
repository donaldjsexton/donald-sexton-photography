<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        @page { margin: 28mm 24mm; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #2d1d15;
            font-size: 11pt;
            line-height: 1.45;
            margin: 0;
        }
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
            border-bottom: 1px solid #d9c8b8;
            padding-bottom: 18px;
        }
        .brand h1 { margin: 0; font-size: 18pt; letter-spacing: 0.04em; }
        .brand .meta { color: #6b5446; font-size: 10pt; line-height: 1.5; }
        .invoice-meta { text-align: right; }
        .invoice-meta .number { font-size: 18pt; font-weight: 600; }
        .invoice-meta .label { font-size: 9pt; color: #6b5446; text-transform: uppercase; letter-spacing: 0.08em; margin-top: 4px; }
        .invoice-meta .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 9pt;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            background: #f1e7da;
            color: #6b5446;
            margin-top: 8px;
        }
        .billing {
            display: flex;
            justify-content: space-between;
            margin-bottom: 28px;
        }
        .billing .col { width: 48%; }
        .billing h3 {
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #6b5446;
            margin: 0 0 6px;
        }
        .billing p { margin: 0; line-height: 1.5; }

        table.lines {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        table.lines th {
            text-align: left;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b5446;
            border-bottom: 1px solid #d9c8b8;
            padding: 10px 8px;
        }
        table.lines td {
            padding: 12px 8px;
            border-bottom: 1px solid #f1e7da;
            vertical-align: top;
        }
        table.lines .num { text-align: right; white-space: nowrap; }

        .totals { width: 280px; margin-left: auto; margin-bottom: 24px; }
        .totals tr td { padding: 6px 8px; }
        .totals tr td:last-child { text-align: right; }
        .totals tr.total td {
            border-top: 2px solid #2d1d15;
            font-weight: 700;
            font-size: 12pt;
            padding-top: 10px;
        }

        .schedule {
            margin-bottom: 24px;
        }
        .schedule h3 {
            font-size: 10pt;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #6b5446;
            margin: 0 0 8px;
        }
        .schedule table { width: 100%; border-collapse: collapse; }
        .schedule th, .schedule td {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #f1e7da;
        }
        .schedule .num { text-align: right; }

        .copy { margin-top: 24px; }
        .copy h3 {
            font-size: 10pt;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #6b5446;
            margin: 0 0 6px;
        }
        .copy p { white-space: pre-line; margin: 0 0 14px; }

        .pay-link {
            margin-top: 32px;
            padding: 16px;
            background: #f8f1e8;
            border: 1px solid #d9c8b8;
            text-align: center;
            font-size: 10pt;
        }
        .pay-link a { color: #2d1d15; font-weight: 600; }
    </style>
</head>
<body>
    <div class="doc-header">
        <div class="brand">
            <h1>{{ $brandName }}</h1>
            <p class="meta">
                @if (! empty($brandAddress)){{ $brandAddress }}<br>@endif
                @if (! empty($brandEmail)){{ $brandEmail }}<br>@endif
                @if (! empty($brandPhone)){{ $brandPhone }}@endif
            </p>
        </div>
        <div class="invoice-meta">
            <div class="number">{{ $invoice->number }}</div>
            <div class="label">Invoice</div>
            <div class="status">{{ \App\Models\Invoice::statusOptions()[$invoice->status] ?? $invoice->status }}</div>
        </div>
    </div>

    <div class="billing">
        <div class="col">
            <h3>Bill to</h3>
            <p>
                <strong>{{ $invoice->client?->displayName() }}</strong><br>
                @if ($invoice->client?->email){{ $invoice->client->email }}<br>@endif
                @if ($invoice->client?->phone){{ $invoice->client->phone }}<br>@endif
                @if ($invoice->client?->address_line_1){{ $invoice->client->address_line_1 }}<br>@endif
                @if ($invoice->client?->address_line_2){{ $invoice->client->address_line_2 }}<br>@endif
                {{ collect([$invoice->client?->city, $invoice->client?->state])->filter()->implode(', ') }}
                {{ $invoice->client?->postal_code }}
            </p>
        </div>
        <div class="col" style="text-align:right;">
            <h3>Issued</h3>
            <p>{{ $invoice->issue_date?->format('M j, Y') }}</p>

            @if ($invoice->due_date)
                <h3 style="margin-top:12px;">Due</h3>
                <p>{{ $invoice->due_date->format('M j, Y') }}</p>
            @endif
        </div>
    </div>

    <table class="lines">
        <thead>
            <tr>
                <th>Description</th>
                <th class="num">Qty</th>
                <th class="num">Unit</th>
                <th class="num">Tax</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lineItems as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                    <td class="num">${{ number_format($item->unit_price_cents / 100, 2) }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($item->tax_rate, 2), '0'), '.') }}%</td>
                    <td class="num">${{ number_format($item->total_cents / 100, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td>${{ number_format($invoice->subtotal_cents / 100, 2) }}</td>
        </tr>
        @if ($invoice->discount_cents > 0)
            <tr>
                <td>Discount</td>
                <td>−${{ number_format($invoice->discount_cents / 100, 2) }}</td>
            </tr>
        @endif
        <tr>
            <td>Tax</td>
            <td>${{ number_format($invoice->tax_cents / 100, 2) }}</td>
        </tr>
        <tr class="total">
            <td>Total</td>
            <td>${{ number_format($invoice->total_cents / 100, 2) }}</td>
        </tr>
        @if ($invoice->amount_paid_cents > 0)
            <tr>
                <td>Paid</td>
                <td>−${{ number_format($invoice->amount_paid_cents / 100, 2) }}</td>
            </tr>
            <tr class="total">
                <td>Balance due</td>
                <td>${{ number_format($invoice->amountDueCents() / 100, 2) }}</td>
            </tr>
        @endif
    </table>

    @if ($invoice->installments->isNotEmpty())
        <div class="schedule">
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

    @if ($invoice->notes || $invoice->terms)
        <div class="copy">
            @if ($invoice->notes)
                <h3>Notes</h3>
                <p>{{ $invoice->notes }}</p>
            @endif
            @if ($invoice->terms)
                <h3>Terms</h3>
                <p>{{ $invoice->terms }}</p>
            @endif
        </div>
    @endif

    @if (! empty($payUrl) && $invoice->amountDueCents() > 0)
        <div class="pay-link">
            View and pay online: <a href="{{ $payUrl }}">{{ $payUrl }}</a>
        </div>
    @endif
</body>
</html>
