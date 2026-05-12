@extends('portal.layouts.app')

@section('title', 'Contract '.$contract->number)

@section('content')
    <section class="card stack">
        <div style="display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:16px;">
            <div>
                <h3>Contract</h3>
                <h2 style="margin:0;">{{ $contract->number }}</h2>
                <p class="meta" style="margin:6px 0 0;">
                    {{ $contract->title }} · Issued {{ $contract->issue_date?->format('M j, Y') }}
                    @if ($contract->expires_at)
                        · Offer expires {{ $contract->expires_at->format('M j, Y') }}
                    @endif
                </p>
                <span class="pill" style="margin-top:8px;">{{ \App\Models\Contract::statusOptions()[$contract->status] ?? $contract->status }}</span>
            </div>
            <div style="text-align:right;">
                @if ($contract->isSigned())
                    <h3>Signed</h3>
                    <p style="margin:0; font-weight:600;">{{ $contract->signed_at?->format('M j, Y') }}</p>
                    @if ($contract->signer_name)
                        <p class="meta" style="margin:4px 0 0;">by {{ $contract->signer_name }}</p>
                    @endif
                @elseif ($contract->isAwaitingSignature())
                    <h3>Action needed</h3>
                    <p style="margin:0; font-weight:600;">Awaiting your signature</p>
                @endif
            </div>
        </div>

        <div>
            <h3>{{ $contract->title }}</h3>
            <div style="white-space:pre-line; line-height:1.6; font-size:14px;">{{ $contract->body }}</div>
        </div>

        <div style="display:flex; flex-wrap:wrap; gap:12px;">
            <a class="btn btn-secondary" href="{{ route('portal.contracts.pdf', ['contract' => $contract->uuid]) }}" target="_blank" rel="noopener">Download PDF</a>
        </div>
    </section>

    @if ($contract->isAwaitingSignature() && ! $contract->hasExpired())
        <section class="card stack">
            <div>
                <h3>Sign the contract</h3>
                <p style="margin:0;">
                    Type your full legal name and confirm you agree to the terms. We&rsquo;ll record the signature with a timestamp.
                </p>
            </div>

            <form method="POST" action="{{ route('portal.contracts.sign', ['contract' => $contract->uuid]) }}">
                @csrf
                <label class="field">
                    <span>Full legal name</span>
                    <input type="text" name="signer_name" maxlength="255" required autocomplete="name" value="{{ old('signer_name', $contract->signer_name ?? $contract->billableName()) }}">
                </label>

                <label class="field" style="display:flex; gap:10px; align-items:flex-start;">
                    <input type="checkbox" name="agreement" value="1" required style="margin-top:6px;">
                    <span>I have read this contract and agree to its terms. I understand my typed name is my electronic signature and is legally binding.</span>
                </label>

                <button type="submit" class="btn btn-primary">Sign Contract</button>
            </form>
        </section>
    @elseif ($contract->isAwaitingSignature() && $contract->hasExpired())
        <section class="card">
            <h3>Offer expired</h3>
            <p style="margin:0;">This contract&rsquo;s signing window has closed. Please reply to the original email or contact the studio to renegotiate.</p>
        </section>
    @endif
@endsection
