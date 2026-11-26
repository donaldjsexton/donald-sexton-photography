@extends('layouts.admin-v2')

@section('title', 'Invoices')
@section('eyebrow', 'Studio')
@section('heading', 'Invoices')
@section('subheading', 'Drafts, sent, and paid. Filter by status to focus on what needs action.')
@section('header_actions')
    <x-ui.button :href="route('admin.invoices.create')">New Invoice</x-ui.button>
@endsection
@section('content')
    @php
        $cols = 'md:grid-cols-[1fr_2fr_1fr_1fr_1fr_1fr_1fr_auto]';
        $tab = 'rounded-lg px-3 py-1.5 text-sm font-medium';
        $tabOn = 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300';
        $tabOff = 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800';
        $link = 'text-brand-600 hover:underline dark:text-brand-400';
        $tones = ['draft' => 'gray', 'sent' => 'amber', 'paid' => 'green', 'overdue' => 'red', 'void' => 'gray', 'refunded' => 'gray'];
    @endphp

    <x-ui.card>
        <nav class="flex flex-wrap gap-2">
            <a class="{{ $tab }} {{ $currentStatus === 'all' ? $tabOn : $tabOff }}" href="{{ route('admin.invoices.index') }}">All</a>
            @foreach ($statusOptions as $key => $label)
                <a class="{{ $tab }} {{ $currentStatus === $key ? $tabOn : $tabOff }}" href="{{ route('admin.invoices.index', ['status' => $key]) }}">{{ $label }}</a>
            @endforeach
        </nav>
    </x-ui.card>

    <x-ui.card flush class="mt-6 overflow-hidden">
        <div class="hidden gap-4 border-b border-gray-100 bg-gray-50 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-800/60 dark:text-gray-400 md:[display:grid] {{ $cols }}">
            <span>Number</span><span>Client</span><span>Issued</span><span>Due</span><span>Status</span><span class="text-right">Total</span><span class="text-right">Balance</span><span></span>
        </div>
        @forelse ($invoices as $invoice)
            <div class="[display:grid] grid-cols-1 gap-2 border-b border-gray-100 px-5 py-4 text-sm last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60 md:items-center md:gap-4 {{ $cols }}">
                <div class="font-medium text-gray-900 dark:text-white">{{ $invoice->number }}</div>
                <div class="min-w-0 truncate text-gray-700 dark:text-gray-300">
                    @if ($invoice->billable instanceof \App\Models\Client)
                        <a class="{{ $link }}" href="{{ route('admin.clients.show', $invoice->billable) }}">{{ $invoice->billableName() }}</a>
                    @elseif ($invoice->billable instanceof \App\Models\Venue)
                        <a class="{{ $link }}" href="{{ route('admin.venues.edit', $invoice->billable) }}">{{ $invoice->billableName() }}</a>
                        <span class="text-xs text-gray-500 dark:text-gray-400"> · vendor</span>
                    @else
                        <span class="text-gray-500 dark:text-gray-400">—</span>
                    @endif
                </div>
                <div class="text-gray-700 dark:text-gray-300">{{ $invoice->issue_date?->format('M j, Y') ?: '—' }}</div>
                <div class="text-gray-700 dark:text-gray-300">{{ $invoice->due_date?->format('M j, Y') ?: '—' }}</div>
                <div><x-ui.badge :tone="$tones[$invoice->status] ?? 'gray'">{{ $statusOptions[$invoice->status] ?? $invoice->status }}</x-ui.badge></div>
                <div class="text-gray-700 dark:text-gray-300 md:text-right">${{ number_format($invoice->total_cents / 100, 2) }}</div>
                <div class="text-gray-700 dark:text-gray-300 md:text-right">${{ number_format($invoice->amountDueCents() / 100, 2) }}</div>
                <div><a class="{{ $link }}" href="{{ route('admin.invoices.show', $invoice) }}">View</a></div>
            </div>
        @empty
            <p class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No invoices match this filter.</p>
        @endforelse
    </x-ui.card>

    @if ($invoices->hasPages())
        <div class="mt-4">{{ $invoices->links() }}</div>
    @endif
@endsection
