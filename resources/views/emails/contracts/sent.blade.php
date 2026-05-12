<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Contract {{ $contract->number }}</title>
</head>
<body style="margin:0; padding:32px; background:#f6f1ea; color:#2c2018; font:16px/1.6 Arial, sans-serif;">
    <div style="max-width:600px; margin:0 auto; background:#fffdfa; border:1px solid #e6d9ca; padding:40px 32px;">
        <p style="margin:0 0 4px; font-size:11px; letter-spacing:0.2em; text-transform:uppercase; color:#7a6555;">{{ $brandName }}</p>
        <h1 style="margin:0 0 24px; font-size:24px; line-height:1.2; font-weight:400;">Contract {{ $contract->number }}</h1>

        <p style="margin:0 0 16px; color:#4a3f36;">Hi {{ method_exists($contract->billable, 'portalGreeting') ? $contract->billable->portalGreeting() : $contract->billableName() }},</p>

        <p style="margin:0 0 16px; color:#4a3f36;">
            Your contract — <strong>{{ $contract->title }}</strong> — is ready to review and sign. The full document is attached as a PDF, and you can also review and sign it online.
        </p>

        @if ($contract->expires_at)
            <p style="margin:0 0 16px; color:#4a3f36;">
                Please sign by <strong>{{ $contract->expires_at->format('F j, Y') }}</strong>.
            </p>
        @endif

        <p style="margin:0 0 8px; text-align:center;">
            <a
                href="{{ $signUrl }}"
                style="display:inline-block; padding:14px 28px; background:#2d1d15; color:#fffdfa; text-decoration:none; font-weight:600; border-radius:6px; min-height:44px; line-height:24px;"
            >Review &amp; Sign</a>
        </p>
        <p style="margin:0 0 24px; font-size:13px; color:#7a6555; text-align:center; word-break:break-all;">
            Or paste this link into your browser: {{ $signUrl }}
        </p>

        <p style="margin:0 0 24px; color:#4a3f36;">
            Reply to this email if you have any questions or want to talk through the terms. Thanks for working with me.
        </p>

        <div style="border-top:1px solid #efe3d7; padding-top:20px; margin-top:8px;">
            <p style="margin:0; font-size:14px; color:#7a6555;">{{ $brandName }}</p>
            <p style="margin:4px 0 0; font-size:13px; color:#a0917f;">Clearwater · Tampa · Destination</p>
        </div>
    </div>
</body>
</html>
