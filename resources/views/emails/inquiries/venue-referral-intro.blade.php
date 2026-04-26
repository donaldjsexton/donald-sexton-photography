@php
    $firstName = trim(explode(' ', (string) $inquiry->primary_name)[0] ?? '');
    $contact = trim((string) $venue->referral_contact_name);
    $venueName = trim((string) $venue->name);
    $referrer = $contact !== '' ? "{$contact} at {$venueName}" : "The team at {$venueName}";
    $preheader = "{$referrer} just connected us — a quick hello and what to expect next.";
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>A note from Donald Sexton Photography</title>
</head>
<body style="margin:0; padding:32px; background:#f6f1ea; color:#2c2018; font:16px/1.6 Arial, sans-serif;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all; font-size:1px; line-height:1px; color:#f6f1ea;">{{ $preheader }}</div>

    <div style="max-width:600px; margin:0 auto; background:#fffdfa; border:1px solid #e6d9ca; padding:40px 32px;">
        <p style="margin:0 0 4px; font-size:11px; letter-spacing:0.2em; text-transform:uppercase; color:#7a6555;">Donald Sexton Photography</p>
        <h1 style="margin:0 0 24px; font-size:24px; line-height:1.2; font-weight:400;">
            @if ($firstName !== '')
                Hi {{ $firstName }},
            @else
                Hello,
            @endif
        </h1>

        <p style="margin:0 0 16px; color:#4a3f36;">{{ $referrer }} passed your details along — congratulations on choosing such a beautiful spot for your wedding.</p>

        @if ($inquiry->event_date)
            <p style="margin:0 0 16px; color:#4a3f36;">I have your date down as <strong>{{ $inquiry->event_date->format('F j, Y') }}</strong>. I will check the calendar and reply within one business day with availability and next steps.</p>
        @else
            <p style="margin:0 0 16px; color:#4a3f36;">I will check the calendar and reply within one business day with next steps.</p>
        @endif

        <p style="margin:0 0 16px; color:#4a3f36;">In the meantime, a few recent wedding stories are the easiest way to get a feel for how I work.</p>

        <p style="margin:0 0 24px;">
            <a href="{{ route('weddings.index') }}" style="display:inline-block; padding:12px 22px; background:#2c2018; color:#fffdfa; text-decoration:none; letter-spacing:0.05em; font-size:14px;">See Wedding Stories</a>
        </p>

        <p style="margin:0 0 16px; color:#4a3f36;">If anything immediate comes up — a question about coverage, timeline, or anything else — just reply to this note. I read every message myself.</p>

        <div style="border-top:1px solid #efe3d7; padding-top:20px; margin-top:8px;">
            <p style="margin:0; font-size:14px; color:#7a6555;">Donald Sexton</p>
            <p style="margin:4px 0 0; font-size:13px; color:#a0917f;">Clearwater &middot; Tampa &middot; Destination</p>
        </div>
    </div>
</body>
</html>
