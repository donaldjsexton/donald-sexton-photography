@extends('layouts.admin-v2')

@section('title', $contract->number)
@section('eyebrow', 'Studio')
@section('heading', $contract->number)
@section('subheading', $contract->billableName().' · '.\App\Models\Contract::statusOptions()[$contract->status])
@section('header_actions')
    <div class="flex flex-wrap gap-2">
        <x-ui.button variant="secondary" :href="route('admin.contracts.pdf', $contract)" target="_blank" rel="noopener">Download PDF</x-ui.button>
        @if ($contract->isEditable())
            <x-ui.button variant="secondary" :href="route('admin.contracts.edit', $contract)">Edit</x-ui.button>
        @endif
    </div>
@endsection
@section('content')
    @php
        $link = 'text-brand-600 hover:underline dark:text-brand-400';
        $label = 'text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
        $muted = 'text-sm text-gray-500 dark:text-gray-400';
        $val = 'font-medium text-gray-900 dark:text-white';
    @endphp
    <div class="space-y-6">
        <x-ui.card>
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                <div>
                    <p class="{{ $label }}">Counterparty</p>
                    <p class="{{ $val }}">{{ $contract->billableName() }}</p>
                    <p class="{{ $muted }}">{{ $contract->billableEmail() ?: 'No email on file' }}</p>
                    @if ($contract->billable instanceof \App\Models\Venue)
                        <p class="{{ $muted }}">Vendor / venue</p>
                    @elseif ($contract->billable instanceof \App\Models\Client && $contract->billable->company)
                        <p class="{{ $muted }}">{{ $contract->billable->company }}</p>
                    @endif
                </div>
                <div>
                    <p class="{{ $label }}">Issued</p>
                    <p class="{{ $val }}">{{ $contract->issue_date?->format('M j, Y') }}</p>
                    @if ($contract->expires_at)
                        <p class="{{ $label }} mt-3">Expires</p>
                        <p class="{{ $val }}">{{ $contract->expires_at->format('M j, Y') }}</p>
                    @endif
                </div>
                <div>
                    <p class="{{ $label }}">Status</p>
                    <p class="{{ $val }}">{{ \App\Models\Contract::statusOptions()[$contract->status] ?? $contract->status }}</p>
                    @if ($contract->signed_at)
                        <p class="{{ $label }} mt-3">Signed</p>
                        <p class="{{ $val }}">{{ $contract->signed_at->format('M j, Y g:i a') }}</p>
                        @if ($contract->signer_name)
                            <p class="{{ $muted }}">By {{ $contract->signer_name }}</p>
                        @endif
                    @endif
                </div>
            </div>
        </x-ui.card>

        @if ($contract->bookedJob || $contract->invoice)
            <x-ui.card title="Related">
                <ul class="space-y-1 text-sm text-gray-700 dark:text-gray-300">
                    @if ($contract->bookedJob)
                        <li>
                            Booked job:
                            <a class="{{ $link }}" href="{{ route('admin.booked-jobs.show', $contract->bookedJob) }}">
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
                            <a class="{{ $link }}" href="{{ route('admin.invoices.show', $contract->invoice) }}">{{ $contract->invoice->number }}</a>
                            — ${{ number_format($contract->invoice->total_cents / 100, 2) }}
                        </li>
                    @endif
                </ul>
            </x-ui.card>
        @endif

        <x-ui.card :title="$contract->title">
            <div class="whitespace-pre-line text-sm leading-relaxed text-gray-800 dark:text-gray-200">{{ $contract->body }}</div>
        </x-ui.card>

        @if ($contract->isSigned())
            <x-ui.card title="Signature">
                <p class="text-sm text-gray-900 dark:text-white">
                    <strong>{{ $contract->signer_name }}</strong>
                    @if ($contract->signer_email)
                        <span class="{{ $muted }}"> · {{ $contract->signer_email }}</span>
                    @endif
                </p>
                <p class="mt-1.5 {{ $muted }}">
                    Signed {{ $contract->signed_at?->format('M j, Y g:i a') }}
                    @if ($contract->signer_ip)
                        from IP {{ $contract->signer_ip }}
                    @endif
                </p>
                @if ($contract->signer_user_agent)
                    <p class="mt-1 break-all text-xs text-gray-500 dark:text-gray-400">{{ $contract->signer_user_agent }}</p>
                @endif
            </x-ui.card>
        @endif

        @if ($contract->internal_notes)
            <x-ui.card title="Internal notes">
                <p class="whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $contract->internal_notes }}</p>
            </x-ui.card>
        @endif

        <x-ui.card title="Actions">
            <div class="flex flex-wrap gap-3">
                @if ($contract->isProposal() && in_array($contract->status, [\App\Models\Contract::STATUS_DRAFT, \App\Models\Contract::STATUS_SENT], true))
                    <form method="POST" action="{{ route('admin.contracts.send-proposal', $contract) }}" onsubmit="return confirm('Email this booking proposal (contract + invoice {{ $contract->invoice?->number }}) to {{ $contract->billableEmail() }}?');">
                        @csrf
                        <x-ui.button type="submit">
                            {{ $contract->status === \App\Models\Contract::STATUS_SENT ? 'Re-send Proposal' : 'Send as Proposal' }}
                        </x-ui.button>
                    </form>
                @endif

                @if ($contract->status === \App\Models\Contract::STATUS_DRAFT)
                    <form method="POST" action="{{ route('admin.contracts.send', $contract) }}" onsubmit="return confirm('Email this contract to {{ $contract->billableEmail() }}?');">
                        @csrf
                        <x-ui.button :variant="$contract->isProposal() ? 'secondary' : 'primary'" type="submit">Send Contract Only</x-ui.button>
                    </form>
                @elseif ($contract->status === \App\Models\Contract::STATUS_SENT)
                    <form method="POST" action="{{ route('admin.contracts.send', $contract) }}" onsubmit="return confirm('Re-send this contract to {{ $contract->billableEmail() }}?');">
                        @csrf
                        <x-ui.button variant="secondary" type="submit">Re-send Contract Only</x-ui.button>
                    </form>
                @endif

                @if ($contract->status !== \App\Models\Contract::STATUS_VOID)
                    <form method="POST" action="{{ route('admin.contracts.void', $contract) }}" onsubmit="return confirm('Void this contract? It will no longer be valid.');">
                        @csrf
                        <x-ui.button variant="secondary" type="submit">Void</x-ui.button>
                    </form>
                @endif

                @if ($contract->isEditable())
                    <form method="POST" action="{{ route('admin.contracts.destroy', $contract) }}" onsubmit="return confirm('Delete this draft contract? This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <x-ui.button variant="danger" type="submit">Delete Draft</x-ui.button>
                    </form>
                @endif
            </div>
        </x-ui.card>
    </div>
@endsection
