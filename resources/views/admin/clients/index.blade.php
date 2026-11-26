@extends('layouts.admin-v2')

@section('title', 'Clients')
@section('eyebrow', 'Studio')
@section('heading', 'Clients')
@section('subheading', 'People and couples you invoice. Convert qualified inquiries into clients here.')
@section('header_actions')
    <x-ui.button :href="route('admin.clients.create')">New client</x-ui.button>
@endsection
@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
        $cols = 'md:grid-cols-[2fr_2fr_1fr_1fr_1fr_auto]';
    @endphp

    <form method="GET" action="{{ route('admin.clients.index') }}">
        <x-ui.card>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <x-ui.field label="Search clients" class="flex-1">
                    <input type="search" name="search" value="{{ $search }}" placeholder="Name, email, or company" class="{{ $input }}">
                </x-ui.field>
                <div class="flex items-center gap-3">
                    <x-ui.button type="submit">Apply</x-ui.button>
                    @if ($search !== '')
                        <a href="{{ route('admin.clients.index') }}" class="text-sm text-gray-500 underline dark:text-gray-400">Clear</a>
                    @endif
                </div>
            </div>
        </x-ui.card>
    </form>

    <x-ui.card flush class="mt-6 overflow-hidden">
        <div class="hidden gap-4 border-b border-gray-100 bg-gray-50 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-800/60 dark:text-gray-400 md:[display:grid] {{ $cols }}">
            <span>Name</span><span>Email</span><span>Phone</span><span>Location</span><span>Invoices</span><span></span>
        </div>
        @forelse ($clients as $client)
            <div class="[display:grid] grid-cols-1 gap-2 border-b border-gray-100 px-5 py-4 text-sm last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60 md:items-center md:gap-4 {{ $cols }}">
                <div class="min-w-0">
                    <a href="{{ route('admin.clients.show', $client) }}" class="block truncate font-medium text-gray-900 dark:text-white">{{ $client->displayName() }}</a>
                    @if ($client->company)
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $client->company }}</p>
                    @endif
                </div>
                <div class="min-w-0 truncate text-gray-700 dark:text-gray-300">{{ $client->email }}</div>
                <div class="text-gray-700 dark:text-gray-300">{{ $client->phone ?: '—' }}</div>
                <div class="text-gray-700 dark:text-gray-300">{{ collect([$client->city, $client->state])->filter()->implode(', ') ?: '—' }}</div>
                <div class="text-gray-700 dark:text-gray-300">{{ $client->invoices_count }}</div>
                <div class="flex gap-3 text-sm">
                    <a href="{{ route('admin.clients.show', $client) }}" class="text-brand-600 hover:underline dark:text-brand-400">View</a>
                    <a href="{{ route('admin.clients.edit', $client) }}" class="text-brand-600 hover:underline dark:text-brand-400">Edit</a>
                </div>
            </div>
        @empty
            <p class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No clients yet. Create one or convert an inquiry.</p>
        @endforelse
    </x-ui.card>

    @if ($clients->hasPages())
        <div class="mt-4">{{ $clients->links() }}</div>
    @endif
@endsection
