@extends('portal.layouts.app')

@section('title', 'Booking proposal '.$contract->number)

@section('content')
    <section class="card stack">
        <div>
            <h3>Booking proposal</h3>
            <h2 style="margin:0;">{{ $contract->number }}</h2>
            <p class="meta" style="margin:6px 0 0;">
                {{ $contract->title }}
                @if ($contract->bookedJob?->event_date)
                    · {{ $contract->bookedJob->event_date->format('M j, Y') }}
                @elseif ($contract->expires_at)
                    · Please respond by {{ $contract->expires_at->format('M j, Y') }}
                @endif
            </p>
        </div>
        <p style="margin:0;">
            Two steps to lock in your date: review and sign the agreement, then pay the deposit.
        </p>
    </section>

    {{-- Step 1 — Contract --}}
    <section class="card stack">
        <div style="display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:16px;">
            <div>
                <h3>Step 1 · Sign the agreement</h3>
                <h2 style="margin:0;">{{ $contract->title }}</h2>
            </div>
            <div style="text-align:right;">
                @if ($contract->isSigned())
                    <span class="pill">Signed</span>
                    <p class="meta" style="margin:6px 0 0;">{{ $contract->signed_at?->format('M j, Y') }}</p>
                @elseif ($contract->isAwaitingSignature())
                    <span class="pill">Awaiting signature</span>
                @endif
            </div>
        </div>

        <div>
            <div style="white-space:pre-line; line-height:1.6; font-size:14px;">{{ $contract->body }}</div>
        </div>

        <div style="display:flex; flex-wrap:wrap; gap:12px;">
            <a class="btn btn-secondary" href="{{ route('portal.contracts.pdf', ['contract' => $contract->uuid]) }}" target="_blank" rel="noopener">Download contract PDF</a>
        </div>

        @if ($contract->isAwaitingSignature() && ! $contract->hasExpired())
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
        @elseif ($contract->isAwaitingSignature() && $contract->hasExpired())
            <p style="margin:0;">This proposal&rsquo;s signing window has closed. Please contact the studio to renegotiate.</p>
        @endif
    </section>

    {{-- Step 2 — Invoice --}}
    @if ($invoice)
        <section class="card stack">
            <div style="display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:16px;">
                <div>
                    <h3>Step 2 · Pay the deposit</h3>
                    <h2 style="margin:0;">{{ $invoice->number }}</h2>
                    <p class="meta" style="margin:6px 0 0;">
                        Issued {{ $invoice->issue_date?->format('M j, Y') }}
                        @if ($invoice->due_date)
                            · Due {{ $invoice->due_date->format('M j, Y') }}
                        @endif
                    </p>
                </div>
                <div style="text-align:right;">
                    <h3>Balance due</h3>
                    <p style="margin:0; font-size:24px; font-weight:600;">${{ number_format($invoice->amountDueCents() / 100, 2) }}</p>
                    <p class="meta" style="margin:4px 0 0;">of ${{ number_format($invoice->total_cents / 100, 2) }} total</p>
                </div>
            </div>

            @if ($invoice->isPaid())
                <p style="margin:0;"><span class="pill">Paid</span> &nbsp;Thank you — your booking is confirmed.</p>
            @elseif (! $contract->isSigned())
                <p style="margin:0;">Sign the agreement above to unlock payment.</p>
                <button class="btn btn-primary" type="button" disabled style="opacity:0.5; cursor:not-allowed;">Pay deposit</button>
            @else
                <p style="margin:0;">Your agreement is signed. Choose a payment method to confirm your booking.</p>
                <a class="btn btn-primary" href="{{ route('portal.invoices.show', ['invoice' => $invoice->uuid]) }}">Pay deposit</a>
                <a class="btn btn-secondary" href="{{ route('portal.invoices.pdf', ['invoice' => $invoice->uuid]) }}" target="_blank" rel="noopener">Download invoice PDF</a>
            @endif
        </section>
    @endif
@endsection
