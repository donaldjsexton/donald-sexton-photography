@php
    $venue = $inquiry->venue?->name ?: $inquiry->venue_name ?: 'Not shared yet';
    $eventDate = $inquiry->event_date?->format('F j, Y') ?: 'Not shared yet';
    $guestCount = $inquiry->guest_count_range ?: 'Not shared yet';
    $budget = $inquiry->budget_range ?: 'Not shared yet';
    $city = $inquiry->location_city ?: 'Not shared yet';
    $partner = $inquiry->partner_name ?: 'Not shared yet';
    $message = trim((string) $inquiry->message);
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>New Inquiry</title>
</head>
<body style="margin:0; padding:32px; background:#f6f1ea; color:#2c2018; font:16px/1.6 Arial, sans-serif;">
    <div style="max-width:680px; margin:0 auto; background:#fffdfa; border:1px solid #e6d9ca; padding:32px;">
        <p style="margin:0 0 8px; font-size:12px; letter-spacing:0.16em; text-transform:uppercase; color:#7a6555;">New Inquiry</p>
        <h1 style="margin:0 0 20px; font-size:28px; line-height:1.15;">{{ $inquiry->primary_name }} sent a new check availability form.</h1>

        <table style="width:100%; border-collapse:collapse; margin:0 0 24px;">
            <tbody>
                <tr>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7; font-weight:700;">Name</td>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7;">{{ $inquiry->primary_name }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7; font-weight:700;">Partner</td>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7;">{{ $partner }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7; font-weight:700;">Email</td>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7;"><a href="mailto:{{ $inquiry->email }}" style="color:#2c2018;">{{ $inquiry->email }}</a></td>
                </tr>
                <tr>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7; font-weight:700;">Phone</td>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7;">{{ $inquiry->phone ?: 'Not shared yet' }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7; font-weight:700;">Event</td>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7;">{{ ucfirst($inquiry->event_type) }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7; font-weight:700;">Date</td>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7;">{{ $eventDate }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7; font-weight:700;">Venue</td>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7;">{{ $venue }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7; font-weight:700;">City</td>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7;">{{ $city }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7; font-weight:700;">Guest count</td>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7;">{{ $guestCount }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7; font-weight:700;">Budget</td>
                    <td style="padding:10px 0; border-bottom:1px solid #efe3d7;">{{ $budget }}</td>
                </tr>
            </tbody>
        </table>

        <h2 style="margin:0 0 8px; font-size:18px;">Message</h2>
        <p style="margin:0; white-space:pre-line;">{{ $message !== '' ? $message : 'No message was shared.' }}</p>
    </div>
</body>
</html>
