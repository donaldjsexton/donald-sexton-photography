<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment received for invoice {{ $invoice->number }}</title>
</head>
<body style="margin:0; padding:32px; background:#f6f1ea; color:#2c2018; font:16px/1.6 Arial, sans-serif;">
    <div style="max-width:600px; margin:0 auto; background:#fffdfa; border:1px solid #e6d9ca; padding:40px 32px;">
        <p style="margin:0 0 4px; font-size:11px; letter-spacing:0.2em; text-transform:uppercase; color:#7a6555;">{{ $brandName }}</p>
        <h1 style="margin:0 0 24px; font-size:24px; line-height:1.2; font-weight:400;">Payment received — thank you</h1>

        <p style="margin:0 0 16px; color:#4a3f36;">Hi {{ method_exists($invoice->billable, 'portalGreeting') ? $invoice->billable->portalGreeting() : $invoice->billableName() }},</p>

        <p style="margin:0 0 16px; color:#4a3f36;">
            Your payment has been received and invoice <strong>{{ $invoice->number }}</strong> is now marked as paid. This email is your receipt — keep it for your records.
        </p>

        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:0 0 24px; border-collapse:collapse;">
            <tr>
                <td style="padding:12px 16px; background:#f1e7da; border-radius:6px;">
                    <p style="margin:0; font-size:11px; letter-spacing:0.1em; text-transform:uppercase; color:#7a6555;">Amount paid</p>
                    <p style="margin:4px 0 0; font-size:22px; font-weight:600; color:#2c2018;">${{ number_format($invoice->total_cents / 100, 2) }}</p>
                    @if ($invoice->paid_at)
                        <p style="margin:4px 0 0; font-size:13px; color:#7a6555;">Paid {{ $invoice->paid_at->format('F j, Y') }}</p>
                    @endif
                </td>
            </tr>
        </table>

        <p style="margin:0 0 8px; text-align:center;">
            <a
                href="{{ $viewUrl }}"
                style="display:inline-block; padding:14px 28px; background:#2d1d15; color:#fffdfa; text-decoration:none; font-weight:600; border-radius:6px; min-height:44px; line-height:24px;"
            >View Invoice</a>
        </p>
        <p style="margin:0 0 24px; font-size:13px; color:#7a6555; text-align:center; word-break:break-all;">
            Or paste this link into your browser: {{ $viewUrl }}
        </p>

        <p style="margin:0 0 24px; color:#4a3f36;">
            Thanks so much for the trust — it means a lot. Reply to this email if you need anything at all.
        </p>

        <div style="border-top:1px solid #efe3d7; padding-top:20px; margin-top:8px;">
            <p style="margin:0; font-size:14px; color:#7a6555;">{{ $brandName }}</p>
            <p style="margin:4px 0 0; font-size:13px; color:#a0917f;">Clearwater · Tampa · Destination</p>
        </div>
    </div>
</body>
</html>
