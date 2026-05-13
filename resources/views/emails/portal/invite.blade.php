<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Your {{ $brandName }} client portal</title>
</head>
<body style="margin:0; padding:32px; background:#f6f1ea; color:#2c2018; font:16px/1.6 Arial, sans-serif;">
    <div style="max-width:600px; margin:0 auto; background:#fffdfa; border:1px solid #e6d9ca; padding:40px 32px;">
        <p style="margin:0 0 4px; font-size:11px; letter-spacing:0.2em; text-transform:uppercase; color:#7a6555;">{{ $brandName }}</p>
        <h1 style="margin:0 0 24px; font-size:24px; line-height:1.2; font-weight:400;">Welcome, {{ $client->portalGreeting() }}.</h1>

        <p style="margin:0 0 16px; color:#4a3f36;">Your client portal is ready. From here you&rsquo;ll be able to view and sign contracts, pay invoices, and keep track of everything tied to your booking in one place.</p>

        <p style="margin:0 0 24px; color:#4a3f36;">Use the link below to set your password and sign in. It expires in 7 days.</p>

        <p style="margin:0 0 32px;">
            <a href="{{ $setupUrl }}" style="display:inline-block; background:#2d1d15; color:#fffdfa; padding:12px 22px; border-radius:8px; text-decoration:none; font-weight:600;">Set up your portal</a>
        </p>

        <p style="margin:0 0 8px; color:#7a6555; font-size:13px;">If the button doesn&rsquo;t work, copy and paste this URL:</p>
        <p style="margin:0 0 24px; color:#4a3f36; font-size:13px; word-break:break-all;">{{ $setupUrl }}</p>

        <div style="border-top:1px solid #efe3d7; padding-top:20px; margin-top:8px;">
            <p style="margin:0; font-size:14px; color:#7a6555;">Donald Sexton</p>
            <p style="margin:4px 0 0; font-size:13px; color:#a0917f;">Clearwater &middot; Tampa &middot; Destination</p>
        </div>
    </div>
</body>
</html>
