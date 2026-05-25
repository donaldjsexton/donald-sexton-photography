<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Contract {{ $contract->number }} fully executed</title>
</head>
<body style="margin:0; padding:32px; background:#f6f1ea; color:#2c2018; font:16px/1.6 Arial, sans-serif;">
    <div style="max-width:600px; margin:0 auto; background:#fffdfa; border:1px solid #e6d9ca; padding:40px 32px;">
        <p style="margin:0 0 4px; font-size:11px; letter-spacing:0.2em; text-transform:uppercase; color:#7a6555;">{{ $brandName }}</p>
        <h1 style="margin:0 0 24px; font-size:24px; line-height:1.2; font-weight:400;">Your contract is fully executed</h1>

        <p style="margin:0 0 16px; color:#4a3f36;">
            <strong>{{ $contract->number }} — {{ $contract->title }}</strong> has now been
            signed by you and counter-signed by {{ $brandName }}. It is fully executed.
        </p>

        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:0 0 24px; border-collapse:collapse;">
            <tr>
                <td style="padding:12px 16px; background:#f1e7da; border-radius:6px;">
                    <p style="margin:0; font-size:11px; letter-spacing:0.1em; text-transform:uppercase; color:#7a6555;">Signed by you</p>
                    <p style="margin:4px 0 0; font-size:16px; font-weight:600; color:#2c2018;">{{ $contract->signer_name }}</p>
                    <p style="margin:2px 0 0; font-size:13px; color:#7a6555;">{{ $contract->signed_at?->format('F j, Y \a\t g:i a') }}</p>

                    <p style="margin:14px 0 0; font-size:11px; letter-spacing:0.1em; text-transform:uppercase; color:#7a6555;">Counter-signed by</p>
                    <p style="margin:4px 0 0; font-size:16px; font-weight:600; color:#2c2018;">{{ $contract->countersigner_name }}</p>
                    <p style="margin:2px 0 0; font-size:13px; color:#7a6555;">{{ $contract->countersigned_at?->format('F j, Y \a\t g:i a') }}</p>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 24px; color:#4a3f36;">
            The fully-executed PDF is attached for your records.
        </p>

        <div style="border-top:1px solid #efe3d7; padding-top:20px; margin-top:8px;">
            <p style="margin:0; font-size:14px; color:#7a6555;">{{ $brandName }}</p>
        </div>
    </div>
</body>
</html>
