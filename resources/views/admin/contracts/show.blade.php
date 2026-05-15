@extends('layouts.admin')

@section('title', $contract->number)
@section('eyebrow', 'Studio')
@section('heading', $contract->number)
@section('subheading', $contract->billableName().' · '.\App\Models\Contract::statusOptions()[$contract->status])
@section('header_actions')
    <a class="cta-secondary" href="{{ route('admin.contracts.pdf', $contract) }}" target="_blank" rel="noopener">Download PDF</a>
    @if ($contract->isEditable())
        <a class="cta-secondary" href="{{ route('admin.contracts.edit', $contract) }}">Edit</a>
    @endif
@endsection
@section('content')
    <section class="admin-card">
        <div class="field-grid">
            <div>
                <p class="eyebrow">Counterparty</p>
                <strong>{{ $contract->billableName() }}</strong><br>
                <span class="meta">{{ $contract->billableEmail() ?: 'No email on file' }}</span>
                @if ($contract->billable instanceof \App\Models\Venue)
                    <br><span class="meta">Vendor / venue</span>
                @elseif ($contract->billable instanceof \App\Models\Client && $contract->billable->company)
                    <br><span class="meta">{{ $contract->billable->company }}</span>
                @endif
            </div>
            <div>
                <p class="eyebrow">Issued</p>
                <strong>{{ $contract->issue_date?->format('M j, Y') }}</strong>
                @if ($contract->expires_at)
                    <p class="eyebrow" style="margin-top:0.75rem;">Expires</p>
                    <strong>{{ $contract->expires_at->format('M j, Y') }}</strong>
                @endif
            </div>
            <div>
                <p class="eyebrow">Status</p>
                <strong>{{ \App\Models\Contract::statusOptions()[$contract->status] ?? $contract->status }}</strong>
                @if ($contract->signed_at)
                    <p class="eyebrow" style="margin-top:0.75rem;">Signed</p>
                    <strong>{{ $contract->signed_at->format('M j, Y g:i a') }}</strong>
                    @if ($contract->signer_name)
                        <br><span class="meta">By {{ $contract->signer_name }}</span>
                    @endif
                @endif
            </div>
        </div>
    </section>

    @if ($contract->bookedJob || $contract->invoice)
        <section class="admin-card">
            <h3>Related</h3>
            <ul class="meta" style="margin:0;">
                @if ($contract->bookedJob)
                    <li>
                        Booked job:
                        <a href="{{ route('admin.booked-jobs.show', $contract->bookedJob) }}">
                            {{ $contract->bookedJob->summary }}
                        </a>
                        @if ($contract->bookedJob->event_date)
                            — {{ $contract->bookedJob->event_date->format('M j, Y') }}
                        @endif
                    </li>
                @endif
                @if ($contract->invoice)
                    <li>
                        Invoice:
                        <a href="{{ route('admin.invoices.show', $contract->invoice) }}">{{ $contract->invoice->number }}</a>
                        — ${{ number_format($contract->invoice->total_cents / 100, 2) }}
                    </li>
                @endif
            </ul>
        </section>
    @endif

    <section class="admin-card">
        <h3>{{ $contract->title }}</h3>
        <div style="white-space:pre-line; line-height:1.6;">{{ $contract->body }}</div>
    </section>

    @if ($contract->isSigned())
        <section class="admin-card">
            <h3>Signature</h3>
            <p style="margin:0;">
                <strong>{{ $contract->signer_name }}</strong>
                @if ($contract->signer_email)
                    <span class="meta"> · {{ $contract->signer_email }}</span>
                @endif
            </p>
            <p class="meta" style="margin-top:6px;">
                Signed {{ $contract->signed_at?->format('M j, Y g:i a') }}
                @if ($contract->signer_ip)
                    from IP {{ $contract->signer_ip }}
                @endif
            </p>
            @if ($contract->signer_user_agent)
                <p class="meta" style="margin-top:4px; word-break:break-all;">{{ $contract->signer_user_agent }}</p>
            @endif
        </section>
    @endif

    @if ($contract->internal_notes)
        <section class="admin-card">
            <h3>Internal notes</h3>
            <p style="white-space:pre-line;">{{ $contract->internal_notes }}</p>
        </section>
    @endif

    <section class="admin-card">
        <h3>Actions</h3>
        <div class="form-actions" style="flex-wrap:wrap; gap:0.75rem;">
            @if ($contract->isProposal() && in_array($contract->status, [\App\Models\Contract::STATUS_DRAFT, \App\Models\Contract::STATUS_SENT], true))
                <form method="POST" action="{{ route('admin.contracts.send-proposal', $contract) }}" onsubmit="return confirm('Email this booking proposal (contract + invoice {{ $contract->invoice?->number }}) to {{ $contract->billableEmail() }}?');">
                    @csrf
                    <button class="cta" type="submit">
                        {{ $contract->status === \App\Models\Contract::STATUS_SENT ? 'Re-send Proposal' : 'Send as Proposal' }}
                    </button>
                </form>
            @endif

            @if ($contract->status === \App\Models\Contract::STATUS_DRAFT)
                <form method="POST" action="{{ route('admin.contracts.send', $contract) }}" onsubmit="return confirm('Email this contract to {{ $contract->billableEmail() }}?');">
                    @csrf
                    <button class="{{ $contract->isProposal() ? 'cta-secondary' : 'cta' }}" type="submit">Send Contract Only</button>
                </form>
            @elseif ($contract->status === \App\Models\Contract::STATUS_SENT)
                <form method="POST" action="{{ route('admin.contracts.send', $contract) }}" onsubmit="return confirm('Re-send this contract to {{ $contract->billableEmail() }}?');">
                    @csrf
                    <button class="cta-secondary" type="submit">Re-send Contract Only</button>
                </form>
            @endif

            @if ($contract->status !== \App\Models\Contract::STATUS_VOID)
                <form method="POST" action="{{ route('admin.contracts.void', $contract) }}" onsubmit="return confirm('Void this contract? It will no longer be valid.');">
                    @csrf
                    <button class="cta-secondary" type="submit">Void</button>
                </form>
            @endif

            @if ($contract->isEditable())
                <form method="POST" action="{{ route('admin.contracts.destroy', $contract) }}" onsubmit="return confirm('Delete this draft contract? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button class="cta-secondary" type="submit" style="color:#a03030;">Delete Draft</button>
                </form>
            @endif
        </div>
    </section>
@endsection
