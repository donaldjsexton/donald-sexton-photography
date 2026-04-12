<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Thank you for your inquiry</title>
</head>
<body style="margin:0; padding:32px; background:#f6f1ea; color:#2c2018; font:16px/1.6 Arial, sans-serif;">
    <div style="max-width:600px; margin:0 auto; background:#fffdfa; border:1px solid #e6d9ca; padding:40px 32px;">
        <p style="margin:0 0 4px; font-size:11px; letter-spacing:0.2em; text-transform:uppercase; color:#7a6555;">Donald Sexton Photography</p>
        <h1 style="margin:0 0 24px; font-size:24px; line-height:1.2; font-weight:400;">Thank you, {{ $inquiry->primary_name }}.</h1>

        <p style="margin:0 0 16px; color:#4a3f36;">I received your note and will be in touch soon — usually within one business day.</p>

        @if ($inquiry->event_date)
            <p style="margin:0 0 16px; color:#4a3f36;">I see your date is <strong>{{ $inquiry->event_date->format('F j, Y') }}</strong>. I will check availability and get back to you with next steps.</p>
        @endif

        <p style="margin:0 0 24px; color:#4a3f36;">In the meantime, feel free to browse some recent wedding stories to get a feel for how I work.</p>

        <div style="border-top:1px solid #efe3d7; padding-top:20px; margin-top:8px;">
            <p style="margin:0; font-size:14px; color:#7a6555;">Donald Sexton</p>
            <p style="margin:4px 0 0; font-size:13px; color:#a0917f;">Clearwater &middot; Tampa &middot; Destination</p>
        </div>
    </div>
</body>
</html>
