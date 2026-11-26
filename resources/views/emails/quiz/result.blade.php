<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Your wedding coverage plan</title></head>
<body style="margin:0; padding:32px; background:#f6f1ea; color:#2c2018; font:16px/1.6 Arial, sans-serif;">
    <div style="max-width:600px; margin:0 auto; background:#fffdfa; border:1px solid #e6d9ca; padding:40px 32px;">
        <p style="margin:0 0 4px; font-size:11px; letter-spacing:0.2em; text-transform:uppercase; color:#7a6555;">Donald Sexton Photography</p>
        <h1 style="margin:0 0 24px; font-size:24px; line-height:1.2; font-weight:400;">{{ $lead->first_name }}, here's what we'd plan for you: {{ $tier['hours'] }} hours.</h1>
        <p style="margin:0 0 16px; color:#4a3f36;">That's the {{ $tier['label'] }} coverage, from {{ $tier['price'] }}. {{ $tier['intro'] }}</p>
        <p style="margin:0 0 16px; color:#4a3f36;">{{ $why }}</p>
        <h2 style="margin:24px 0 8px; font-size:16px; font-weight:700;">A sample day (for a 4:30 PM ceremony)</h2>
        <table style="border-collapse:collapse; margin:0 0 16px;">
            @foreach ($tier['timeline'] as [$time, $text])
                <tr><td style="padding:4px 14px 4px 0; vertical-align:top; white-space:nowrap; color:#7a6555;">{{ $time }}</td><td style="padding:4px 0; color:#4a3f36;">{{ $text }}</td></tr>
            @endforeach
        </table>
        @if ($tier['gap'])
            <p style="margin:0 0 16px; color:#4a3f36;">{{ $tier['gap'] }}</p>
        @endif
        @if ($second)
            <p style="margin:0 0 16px; color:#4a3f36;">{{ config('quiz.second_photographer') }}</p>
        @endif
        <p style="margin:0 0 16px; color:#4a3f36;">Every wedding includes the full edited gallery, and we sit down and look through it together on a big screen.</p>
        <p style="margin:24px 0;"><a href="{{ $inquiryUrl }}" style="background:#2c2018; color:#fffdfa; padding:12px 22px; text-decoration:none;">Check my date</a></p>
        <p style="margin:0 0 24px; color:#4a3f36;">Or just reply to this email. It comes straight to me.</p>
        <div style="border-top:1px solid #efe3d7; padding-top:20px;">
            <p style="margin:0; font-size:14px; color:#7a6555;">Donald Sexton</p>
            <p style="margin:4px 0 0; font-size:13px; color:#a0917f;">Clearwater &middot; Tampa &middot; Destination</p>
            <p style="margin:16px 0 0; font-size:12px; color:#a0917f;">You got this one email because you finished the coverage quiz. That's the only email we plan to send. <a href="{{ $unsubscribeUrl }}" style="color:#a0917f;">Unsubscribe</a></p>
        </div>
    </div>
</body>
</html>
