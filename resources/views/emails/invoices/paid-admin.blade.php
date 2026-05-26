<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment received — invoice {{ $invoice->number }}</title>
</head>
<body style="margin:0; padding:32px; background:#f6f1ea; color:#2c2018; font:16px/1.6 Arial, sans-serif;">
    <div style="max-width:600px; margin:0 auto; background:#fffdfa; border:1px solid #e6d9ca; padding:40px 32px;">
        <p style="margin:0 0 4px; font-size:11px; letter-spacing:0.2em; text-transform:uppercase; color:#7a6555;">{{ $brandName }}</p>
        <h1 style="margin:0 0 16px; font-size:24px; line-height:1.2; font-weight:400;">Payment received</h1>

        <p style="margin:0 0 16px; color:#4a3f36;">
            Invoice <strong>{{ $invoice->number }}</strong> has been paid in full by <strong>{{ $invoice->billableName() }}</strong>.
        </p>

        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:0 0 24px; border-collapse:collapse; font-size:14px;">
            <tr>
                <td style="padding:8px 0; color:#7a6555; width:40%;">Amount</td>
                <td style="padding:8px 0; color:#2c2018;"><strong>${{ number_format($invoice->total_cents / 100, 2) }} {{ strtoupper($invoice->currency) }}</strong></td>
            </tr>
            <tr>
                <td style="padding:8px 0; color:#7a6555;">Client</td>
                <td style="padding:8px 0; color:#2c2018;">
                    {{ $invoice->billableName() }}
                    @if ($invoice->billableEmail())
                        <br><span style="color:#7a6555;">{{ $invoice->billableEmail() }}</span>
                    @endif
                </td>
            </tr>
            @if ($invoice->paid_at)
                <tr>
                    <td style="padding:8px 0; color:#7a6555;">Paid at</td>
                    <td style="padding:8px 0; color:#2c2018;">{{ $invoice->paid_at->format('F j, Y g:ia') }}</td>
                </tr>
            @endif
            @php($latestPayment = $invoice->payments()->orderByDesc('received_at')->first())
            @if ($latestPayment)
                <tr>
                    <td style="padding:8px 0; color:#7a6555;">Gateway</td>
                    <td style="padding:8px 0; color:#2c2018;">
                        {{ ucfirst($latestPayment->gateway) }}
                        @if ($latestPayment->gateway_payment_id)
                            <br><span style="color:#7a6555; font-family:monospace; font-size:12px;">{{ $latestPayment->gateway_payment_id }}</span>
                        @endif
                    </td>
                </tr>
            @endif
        </table>

        <p style="margin:0 0 8px;">
            <a
                href="{{ $adminUrl }}"
                style="display:inline-block; padding:12px 24px; background:#2d1d15; color:#fffdfa; text-decoration:none; font-weight:600; border-radius:6px; min-height:44px; line-height:24px;"
            >Open invoice in admin</a>
        </p>

        <div style="border-top:1px solid #efe3d7; padding-top:20px; margin-top:24px;">
            <p style="margin:0; font-size:13px; color:#a0917f;">Automated notification from {{ $brandName }}.</p>
        </div>
    </div>
</body>
</html>
