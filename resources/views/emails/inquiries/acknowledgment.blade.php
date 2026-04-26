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

        @if (($availability['status'] ?? null) === 'available' && $availability['event_date'])
            <p style="margin:0 0 16px; color:#3f5b3a;"><strong>Good news — {{ $availability['event_date']->format('F j, Y') }} is open on my calendar.</strong> I&rsquo;ll follow up with next steps shortly.</p>
        @elseif (($availability['status'] ?? null) === 'unavailable' && $availability['event_date'])
            <p style="margin:0 0 12px; color:#7a4536;"><strong>{{ $availability['event_date']->format('F j, Y') }} is already on the calendar</strong> — but I wanted to let you know right away rather than leave you waiting.</p>
            @if (! empty($availability['nearby_dates']))
                <p style="margin:0 0 8px; color:#4a3f36;">If a nearby weekend would still work, these are open:</p>
                <ul style="margin:0 0 16px 20px; padding:0; color:#4a3f36;">
                    @foreach ($availability['nearby_dates'] as $date)
                        <li>{{ $date->format('l, F j, Y') }}</li>
                    @endforeach
                </ul>
                <p style="margin:0 0 16px; color:#4a3f36;">Just reply to this email if any of those work, or share a few flexible dates and I&rsquo;ll see what I can do.</p>
            @else
                <p style="margin:0 0 16px; color:#4a3f36;">If you have any flexibility on the date, reply with a few options and I&rsquo;ll see what I can do.</p>
            @endif
        @elseif ($inquiry->event_date)
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
