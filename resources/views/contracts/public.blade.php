<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contract {{ $contract->number }} — {{ config('payments.business.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
    <style>
        :root { color-scheme: light; }
        body { margin: 0; font-family: 'Helvetica Neue', Arial, sans-serif; color: #2d1d15; background: #f6efe6; }
        .doc-shell { max-width: 820px; margin: 0 auto; padding: 32px 20px; }
        .doc-card { background: #fff; border: 1px solid #e7d8c5; border-radius: 12px; padding: 32px; }
        .doc-card__header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 24px; padding-bottom: 18px; border-bottom: 1px solid #e7d8c5; }
        .doc-card__brand h1 { margin: 0; font-size: 22px; letter-spacing: 0.04em; }
        .meta { color: #6b5446; font-size: 13px; line-height: 1.5; }
        .doc-card__meta { text-align: right; }
        .doc-card__meta strong { display: block; font-size: 22px; }
        .pill { display: inline-block; padding: 4px 12px; border-radius: 999px; background: #f1e7da; color: #6b5446; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; margin-top: 8px; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 16px; }
        .btn { display: inline-block; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; min-height: 44px; line-height: 24px; box-sizing: border-box; }
        .btn-primary { background: #2d1d15; color: #fff; }
        .btn-secondary { background: transparent; color: #2d1d15; border: 1px solid #2d1d15; }
        .stack > * + * { margin-top: 24px; }
        .body-copy { white-space: pre-line; line-height: 1.65; font-size: 14px; }
        h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #6b5446; margin: 0 0 8px; }
        @media (max-width: 600px) {
            .doc-card { padding: 20px; border-radius: 8px; }
            .doc-card__header { flex-direction: column; align-items: flex-start; }
            .doc-card__meta { text-align: left; }
        }
    </style>
</head>
<body>
    <div class="doc-shell">
        <div class="doc-card stack">
            <div class="doc-card__header">
                <div class="doc-card__brand">
                    <h1>{{ config('payments.business.name') }}</h1>
                    <p class="meta">
                        @if ($email = config('payments.business.email')){{ $email }}<br>@endif
                        @if ($phone = config('payments.business.phone')){{ $phone }}@endif
                    </p>
                </div>
                <div class="doc-card__meta">
                    <strong>{{ $contract->number }}</strong>
                    <span class="meta">Issued {{ $contract->issue_date?->format('M j, Y') }}</span>
                    @if ($contract->expires_at)
                        <br><span class="meta">Offer expires {{ $contract->expires_at->format('M j, Y') }}</span>
                    @endif
                    <div class="pill">{{ \App\Models\Contract::statusOptions()[$contract->status] ?? $contract->status }}</div>
                </div>
            </div>

            <div>
                <h3>Between</h3>
                <p style="margin:0;"><strong>{{ config('payments.business.name') }}</strong></p>
                <p style="margin:6px 0 0;"><strong>{{ $contract->billableName() }}</strong><br>
                <span class="meta">{{ $contract->billableEmail() }}</span></p>
            </div>

            <div>
                <h3>{{ $contract->title }}</h3>
                <div class="body-copy">{{ $contract->body }}</div>
            </div>

            @if ($contract->isSigned())
                <div>
                    <h3>Signature</h3>
                    <p style="margin:0;"><strong>{{ $contract->signer_name }}</strong></p>
                    <p class="meta" style="margin:6px 0 0;">
                        Signed {{ $contract->signed_at?->format('M j, Y g:i a') }}
                    </p>
                </div>
            @endif

            <div class="actions">
                <a class="btn btn-secondary" href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('contracts.public.pdf', now()->addDays((int) config('contracts.signed_url_ttl_days', 90)), ['contract' => $contract->uuid]) }}">Download PDF</a>
                @if ($contract->isAwaitingSignature() && ! $contract->hasExpired())
                    <a class="btn btn-primary" href="{{ route('portal.login') }}">Sign in to sign</a>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
