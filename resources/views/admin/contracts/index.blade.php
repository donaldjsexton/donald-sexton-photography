@extends('layouts.admin-v2')

@section('title', 'Contracts')
@section('eyebrow', 'Studio')
@section('heading', 'Contracts')
@section('subheading', 'Drafts, sent, and signed. Filter by status to see what needs action.')
@section('header_actions')
    <div class="flex flex-wrap gap-2">
        <x-ui.button variant="secondary" :href="route('admin.contract-templates.index')">Templates</x-ui.button>
        <x-ui.button :href="route('admin.contracts.create')">New Contract</x-ui.button>
    </div>
@endsection
@section('content')
    @php
        $cols = 'md:grid-cols-[1fr_2fr_2fr_1fr_1fr_1fr_auto]';
        $tab = 'rounded-lg px-3 py-1.5 text-sm font-medium';
        $tabOn = 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300';
        $tabOff = 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800';
        $link = 'text-brand-600 hover:underline dark:text-brand-400';
        $tones = ['draft' => 'gray', 'sent' => 'amber', 'signed' => 'green', 'declined' => 'red', 'void' => 'gray'];
    @endphp

    <x-ui.card>
        <nav class="flex flex-wrap gap-2">
            <a class="{{ $tab }} {{ $currentStatus === 'all' ? $tabOn : $tabOff }}" href="{{ route('admin.contracts.index') }}">All</a>
            @foreach ($statusOptions as $key => $label)
                <a class="{{ $tab }} {{ $currentStatus === $key ? $tabOn : $tabOff }}" href="{{ route('admin.contracts.index', ['status' => $key]) }}">{{ $label }}</a>
            @endforeach
        </nav>
    </x-ui.card>

    <x-ui.card flush class="mt-6 overflow-hidden">
        <div class="hidden gap-4 border-b border-gray-100 bg-gray-50 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-800/60 dark:text-gray-400 md:[display:grid] {{ $cols }}">
            <span>Number</span><span>Counterparty</span><span>Title</span><span>Issued</span><span>Status</span><span>Signed</span><span></span>
        </div>
        @forelse ($contracts as $contract)
            <div class="[display:grid] grid-cols-1 gap-2 border-b border-gray-100 px-5 py-4 text-sm last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60 md:items-center md:gap-4 {{ $cols }}">
                <div class="font-medium text-gray-900 dark:text-white">{{ $contract->number }}</div>
                <div class="min-w-0 truncate text-gray-700 dark:text-gray-300">
                    @if ($contract->billable instanceof \App\Models\Client)
                        <a class="{{ $link }}" href="{{ route('admin.clients.show', $contract->billable) }}">{{ $contract->billableName() }}</a>
                    @elseif ($contract->billable instanceof \App\Models\Venue)
                        <a class="{{ $link }}" href="{{ route('admin.venues.edit', $contract->billable) }}">{{ $contract->billableName() }}</a>
                        <span class="text-xs text-gray-500 dark:text-gray-400"> · vendor</span>
                    @else
                        <span class="text-gray-500 dark:text-gray-400">—</span>
                    @endif
                </div>
                <div class="min-w-0 truncate text-gray-700 dark:text-gray-300">{{ $contract->title }}</div>
                <div class="text-gray-700 dark:text-gray-300">{{ $contract->issue_date?->format('M j, Y') ?: '—' }}</div>
                <div><x-ui.badge :tone="$tones[$contract->status] ?? 'gray'">{{ $statusOptions[$contract->status] ?? $contract->status }}</x-ui.badge></div>
                <div class="text-gray-700 dark:text-gray-300">{{ $contract->signed_at?->format('M j, Y') ?: '—' }}</div>
                <div><a class="{{ $link }}" href="{{ route('admin.contracts.show', $contract) }}">View</a></div>
            </div>
        @empty
            <p class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No contracts match this filter.</p>
        @endforelse
    </x-ui.card>

    @if ($contracts->hasPages())
        <div class="mt-4">{{ $contracts->links() }}</div>
    @endif
@endsection
