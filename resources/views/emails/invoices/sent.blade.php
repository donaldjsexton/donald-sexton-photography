<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
</head>
<body style="margin:0; padding:32px; background:#f6f1ea; color:#2c2018; font:16px/1.6 Arial, sans-serif;">
    <div style="max-width:600px; margin:0 auto; background:#fffdfa; border:1px solid #e6d9ca; padding:40px 32px;">
        <p style="margin:0 0 4px; font-size:11px; letter-spacing:0.2em; text-transform:uppercase; color:#7a6555;">{{ $brandName }}</p>
        <h1 style="margin:0 0 24px; font-size:24px; line-height:1.2; font-weight:400;">Invoice {{ $invoice->number }}</h1>

        <p style="margin:0 0 16px; color:#4a3f36;">Hi {{ method_exists($invoice->billable, 'portalGreeting') ? $invoice->billable->portalGreeting() : $invoice->billableName() }},</p>

        <p style="margin:0 0 16px; color:#4a3f36;">
            Your invoice is ready. The full breakdown is attached as a PDF, and you can also view it online — that page is where you'll be able to pay once online payments are turned on.
        </p>

        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:0 0 24px; border-collapse:collapse;">
            <tr>
                <td style="padding:12px 16px; background:#f1e7da; border-radius:6px;">
                    <p style="margin:0; font-size:11px; letter-spacing:0.1em; text-transform:uppercase; color:#7a6555;">Total due</p>
                    <p style="margin:4px 0 0; font-size:22px; font-weight:600; color:#2c2018;">${{ number_format($invoice->amountDueCents() / 100, 2) }}</p>
                    @if ($invoice->due_date)
                        <p style="margin:4px 0 0; font-size:13px; color:#7a6555;">Due {{ $invoice->due_date->format('F j, Y') }}</p>
                    @endif
                </td>
            </tr>
        </table>

        @if ($invoice->installments->isNotEmpty())
            <p style="margin:0 0 8px; font-size:11px; letter-spacing:0.1em; text-transform:uppercase; color:#7a6555;">Payment schedule</p>
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:0 0 24px; border-collapse:collapse; font-size:14px;">
                @foreach ($invoice->installments as $inst)
                    <tr>
                        <td style="padding:6px 0; border-bottom:1px solid #efe3d7;">
                            <strong>{{ $inst->label ?: 'Installment '.$inst->sequence }}</strong>
                            @if ($inst->due_date)
                                <span style="color:#7a6555;"> · due {{ $inst->due_date->format('M j, Y') }}</span>
                            @endif
                        </td>
                        <td style="padding:6px 0; border-bottom:1px solid #efe3d7; text-align:right;">
                            ${{ number_format($inst->amount_cents / 100, 2) }}
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif

        <p style="margin:0 0 8px; text-align:center;">
            <a
                href="{{ $payUrl }}"
                style="display:inline-block; padding:14px 28px; background:#2d1d15; color:#fffdfa; text-decoration:none; font-weight:600; border-radius:6px; min-height:44px; line-height:24px;"
            >View Invoice Online</a>
        </p>
        <p style="margin:0 0 24px; font-size:13px; color:#7a6555; text-align:center; word-break:break-all;">
            Or paste this link into your browser: {{ $payUrl }}
        </p>

        @if ($invoice->notes)
            <p style="margin:0 0 16px; color:#4a3f36; white-space:pre-line;">{{ $invoice->notes }}</p>
        @endif

        <p style="margin:0 0 24px; color:#4a3f36;">
            Reply to this email if you have any questions or want to talk through the schedule. Thanks for working with me.
        </p>

        <div style="border-top:1px solid #efe3d7; padding-top:20px; margin-top:8px;">
            <p style="margin:0; font-size:14px; color:#7a6555;">{{ $brandName }}</p>
            <p style="margin:4px 0 0; font-size:13px; color:#a0917f;">Clearwater · Tampa · Destination</p>
        </div>
    </div>
</body>
</html>
