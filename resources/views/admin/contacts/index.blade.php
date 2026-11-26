@extends('layouts.admin-v2')

@section('title', 'Contacts')
@section('heading', 'Contacts')
@section('subheading', 'Everyone we have an email for, in one list.')
@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
        $typeTone = ['prospect' => 'amber', 'couple' => 'green', 'past_couple' => 'green'];
        $active = collect($filters)->filter(fn ($v) => $v !== null && $v !== '')->count();
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-ui.stat-card label="Contacts" :value="number_format($totals['contacts'])" icon="users" />
        <x-ui.stat-card label="Do not contact" :value="number_format($totals['suppressed'])" icon="no-symbol" tone="red" />
        <x-ui.stat-card label="Showing" :value="number_format($contacts->total())" icon="magnifying-glass" tone="green" />
    </div>

    <form method="GET" action="{{ route('admin.contacts.index') }}" class="mt-6">
        <x-ui.card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <x-ui.field label="Search" class="sm:col-span-2 lg:col-span-1">
                    <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Name or email" class="{{ $input }}">
                </x-ui.field>
                <x-ui.field label="Type">
                    <select name="type" class="{{ $input }}">
                        <option value="">All</option>
                        @foreach (\App\Models\Contact::TYPES as $key => $label)
                            <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Stage">
                    <select name="stage" class="{{ $input }}">
                        <option value="">All</option>
                        @foreach (\App\Models\Contact::STAGES as $key => $label)
                            <option value="{{ $key }}" @selected($filters['stage'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Source">
                    <select name="source" class="{{ $input }}">
                        <option value="">All</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source }}" @selected($filters['source'] === $source)>{{ $source }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Do not contact">
                    <select name="dnc" class="{{ $input }}">
                        <option value="">Any</option>
                        <option value="1" @selected($filters['dnc'] === '1')>Only DNC</option>
                        <option value="0" @selected($filters['dnc'] === '0')>Not DNC</option>
                    </select>
                </x-ui.field>
            </div>
            <div class="mt-4 flex items-center gap-3">
                <x-ui.button type="submit">Apply</x-ui.button>
                @if ($active)
                    <a href="{{ route('admin.contacts.index') }}" class="text-sm text-gray-500 underline dark:text-gray-400">Clear filters</a>
                @endif
            </div>
        </x-ui.card>
    </form>

    <x-ui.card flush class="mt-6 overflow-hidden">
        <div class="hidden grid-cols-[2fr_1fr_1fr_1fr_1fr] gap-4 border-b border-gray-100 bg-gray-50 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-800/60 dark:text-gray-400 md:grid">
            <span>Contact</span><span>Type</span><span>Stage</span><span>Source</span><span>Last contact</span>
        </div>
        @forelse ($contacts as $contact)
            @php
                $label = $contact->name ?: $contact->email;
                $dnc = $suppressed->has($contact->email);
            @endphp
            <div class="relative grid grid-cols-1 gap-2 border-b border-gray-100 px-5 py-4 text-sm last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60 md:grid-cols-[2fr_1fr_1fr_1fr_1fr] md:items-center md:gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">{{ strtoupper(mb_substr($label, 0, 1)) }}</span>
                    <div class="min-w-0">
                        <a href="{{ route('admin.contacts.show', $contact) }}" class="block truncate font-medium text-gray-900 after:absolute after:inset-0 dark:text-white">{{ $label }}</a>
                        @if ($contact->name)<p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $contact->email }}</p>@endif
                    </div>
                    @if ($dnc)<x-ui.badge tone="red" class="shrink-0">DNC</x-ui.badge>@endif
                </div>
                <div><x-ui.badge :tone="$typeTone[$contact->type] ?? 'gray'">{{ \App\Models\Contact::TYPES[$contact->type] ?? $contact->type }}</x-ui.badge></div>
                <div class="text-gray-700 dark:text-gray-300"><span class="text-xs text-gray-400 md:hidden">Stage: </span>{{ \App\Models\Contact::STAGES[$contact->stage] ?? $contact->stage }}</div>
                <div class="truncate text-gray-500 dark:text-gray-400"><span class="text-xs md:hidden">Source: </span>{{ $contact->source }}</div>
                <div class="text-gray-500 dark:text-gray-400"><span class="text-xs md:hidden">Last contact: </span>{{ optional($contact->last_contact_at)->format('M j, Y') ?: '—' }}</div>
            </div>
        @empty
            <p class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No contacts match.</p>
        @endforelse
    </x-ui.card>

    @if ($contacts->hasPages())
        <div class="mt-4">{{ $contacts->links() }}</div>
    @endif
@endsection
