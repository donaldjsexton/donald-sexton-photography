<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Contract {{ $contract->number }}</title>
    <style>
        @page { margin: 28mm 24mm; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #2d1d15;
            font-size: 11pt;
            line-height: 1.55;
            margin: 0;
        }
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
            border-bottom: 1px solid #d9c8b8;
            padding-bottom: 18px;
        }
        .brand h1 { margin: 0; font-size: 18pt; letter-spacing: 0.04em; }
        .brand .meta { color: #6b5446; font-size: 10pt; line-height: 1.5; }
        .contract-meta { text-align: right; }
        .contract-meta .number { font-size: 18pt; font-weight: 600; }
        .contract-meta .label { font-size: 9pt; color: #6b5446; text-transform: uppercase; letter-spacing: 0.08em; margin-top: 4px; }
        .contract-meta .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 9pt;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            background: #f1e7da;
            color: #6b5446;
            margin-top: 8px;
        }
        .parties {
            display: flex;
            justify-content: space-between;
            margin-bottom: 28px;
        }
        .parties .col { width: 48%; }
        .parties h3 {
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #6b5446;
            margin: 0 0 6px;
        }
        .parties p { margin: 0; line-height: 1.5; }

        h2.title { margin: 0 0 16px; font-size: 14pt; }
        .body { white-space: pre-line; }

        .signature-block {
            margin-top: 32px;
            padding-top: 16px;
            border-top: 1px solid #d9c8b8;
        }
        .signature-block h3 {
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #6b5446;
            margin: 0 0 8px;
        }
        .signature-block .name { font-size: 14pt; font-family: 'Georgia', serif; font-style: italic; margin: 0 0 6px; }
        .signature-block .meta { font-size: 9pt; color: #6b5446; }

        .sign-link {
            margin-top: 32px;
            padding: 16px;
            background: #f8f1e8;
            border: 1px solid #d9c8b8;
            font-size: 10pt;
        }
        .sign-link .label { font-weight: 600; margin-bottom: 4px; }
        .sign-link a {
            color: #2d1d15;
            font-size: 9pt;
            word-break: break-all;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <div class="doc-header">
        <div class="brand">
            <h1>{{ $brandName }}</h1>
            <p class="meta">
                @if (! empty($brandAddress)){{ $brandAddress }}<br>@endif
                @if (! empty($brandEmail)){{ $brandEmail }}<br>@endif
                @if (! empty($brandPhone)){{ $brandPhone }}@endif
            </p>
        </div>
        <div class="contract-meta">
            <div class="number">{{ $contract->number }}</div>
            <div class="label">Contract</div>
            <div class="status">{{ \App\Models\Contract::statusOptions()[$contract->status] ?? $contract->status }}</div>
        </div>
    </div>

    <div class="parties">
        <div class="col">
            <h3>Photographer</h3>
            <p><strong>{{ $brandName }}</strong></p>
            @if ($brandEmail)<p>{{ $brandEmail }}</p>@endif
        </div>
        <div class="col" style="text-align:right;">
            <h3>Client</h3>
            <p><strong>{{ $contract->billableName() }}</strong></p>
            @if ($contract->billableEmail())<p>{{ $contract->billableEmail() }}</p>@endif
            <p style="margin-top:8px;">Issued {{ $contract->issue_date?->format('M j, Y') }}</p>
            @if ($contract->expires_at)
                <p>Offer expires {{ $contract->expires_at->format('M j, Y') }}</p>
            @endif
        </div>
    </div>

    <h2 class="title">{{ $contract->title }}</h2>

    <div class="body">{{ $contract->body }}</div>

    @if ($contract->isSigned())
        <div class="signature-block">
            <h3>Signed by</h3>
            <p class="name">{{ $contract->signer_name }}</p>
            <p class="meta">
                {{ $contract->signed_at?->format('F j, Y \a\t g:i a') }}
                @if ($contract->signer_email)
                    · {{ $contract->signer_email }}
                @endif
                @if ($contract->signer_ip)
                    · IP {{ $contract->signer_ip }}
                @endif
            </p>
        </div>
    @elseif (! empty($signUrl) && ! $contract->isVoided())
        <div class="sign-link">
            <div class="label">View and sign online</div>
            <a href="{{ $signUrl }}">{{ $signUrl }}</a>
        </div>
    @endif
</body>
</html>
