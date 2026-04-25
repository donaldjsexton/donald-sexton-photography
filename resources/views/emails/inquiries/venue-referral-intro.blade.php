<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>A note from Donald Sexton Photography</title>
</head>
<body style="margin:0; padding:32px; background:#f6f1ea; color:#2c2018; font:16px/1.6 Arial, sans-serif;">
    <div style="max-width:600px; margin:0 auto; background:#fffdfa; border:1px solid #e6d9ca; padding:40px 32px;">
        <p style="margin:0 0 4px; font-size:11px; letter-spacing:0.2em; text-transform:uppercase; color:#7a6555;">Donald Sexton Photography</p>
        @php
            $firstName = trim(explode(' ', (string) $inquiry->primary_name)[0] ?? '');
            $contact = trim((string) $venue->referral_contact_name);
        @endphp
        <h1 style="margin:0 0 24px; font-size:24px; line-height:1.2; font-weight:400;">
            @if ($firstName !== '')
                Hi {{ $firstName }},
            @else
                Hello,
            @endif
        </h1>

        <p style="margin:0 0 16px; color:#4a3f36;">
            @if ($contact !== '')
                {{ $contact }} at {{ $venue->name }} passed your details along — congratulations on choosing such a beautiful spot for your wedding.
            @else
                The team at {{ $venue->name }} passed your details along — congratulations on choosing such a beautiful spot for your wedding.
            @endif
        </p>

        @if ($inquiry->event_date)
            <p style="margin:0 0 16px; color:#4a3f36;">I have your date down as <strong>{{ $inquiry->event_date->format('F j, Y') }}</strong>. I will check the calendar and follow up shortly with availability and next steps.</p>
        @else
            <p style="margin:0 0 16px; color:#4a3f36;">I will check the calendar and follow up shortly with next steps.</p>
        @endif

        <p style="margin:0 0 16px; color:#4a3f36;">In the meantime, feel free to browse a few recent wedding stories — it is the easiest way to get a feel for how I work and what your day might look like in photographs.</p>

        <p style="margin:0 0 24px;">
            <a href="{{ url('/weddings') }}" style="display:inline-block; padding:12px 22px; background:#2c2018; color:#fffdfa; text-decoration:none; letter-spacing:0.05em; font-size:14px;">See Wedding Stories</a>
        </p>

        <p style="margin:0 0 16px; color:#4a3f36;">If anything immediate comes up — a question about coverage, timeline, or anything else — just reply to this note. I read every message myself.</p>

        <div style="border-top:1px solid #efe3d7; padding-top:20px; margin-top:8px;">
            <p style="margin:0; font-size:14px; color:#7a6555;">Donald Sexton</p>
            <p style="margin:4px 0 0; font-size:13px; color:#a0917f;">Clearwater &middot; Tampa &middot; Destination</p>
        </div>
    </div>
</body>
</html>
