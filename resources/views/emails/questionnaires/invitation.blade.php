<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Your {{ $brandName }} wedding questionnaire</title>
</head>
<body style="margin:0; padding:32px; background:#f6f1ea; color:#2c2018; font:16px/1.6 Arial, sans-serif;">
    <div style="max-width:600px; margin:0 auto; background:#fffdfa; border:1px solid #e6d9ca; padding:40px 32px;">
        <p style="margin:0 0 4px; font-size:11px; letter-spacing:0.2em; text-transform:uppercase; color:#7a6555;">{{ $brandName }}</p>
        <h1 style="margin:0 0 24px; font-size:24px; line-height:1.2; font-weight:400;">Tell me about your day.</h1>

        <p style="margin:0 0 16px; color:#4a3f36;">I&rsquo;ve put together a short questionnaire to help me show up ready and attentive on your wedding day &mdash; the timeline, the people, and the moments that matter most to you.</p>

        <p style="margin:0 0 24px; color:#4a3f36;">Fill in what you know today and skip anything that doesn&rsquo;t apply. There&rsquo;s no rush &mdash; you can start now and finish later.</p>

        <p style="margin:0 0 32px;">
            <a href="{{ $url }}" style="display:inline-block; background:#2d1d15; color:#fffdfa; padding:12px 22px; border-radius:8px; text-decoration:none; font-weight:600;">Open your questionnaire</a>
        </p>

        <p style="margin:0 0 8px; color:#7a6555; font-size:13px;">If the button doesn&rsquo;t work, copy and paste this URL:</p>
        <p style="margin:0 0 24px; color:#4a3f36; font-size:13px; word-break:break-all;">{{ $url }}</p>

        <div style="border-top:1px solid #efe3d7; padding-top:20px; margin-top:8px;">
            <p style="margin:0; font-size:14px; color:#7a6555;">Donald Sexton</p>
            <p style="margin:4px 0 0; font-size:13px; color:#a0917f;">Clearwater &middot; Tampa &middot; Destination</p>
        </div>
    </div>
</body>
</html>
