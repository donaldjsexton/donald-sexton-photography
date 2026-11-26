@extends('layouts.admin')

@section('title', 'Contacts')
@section('eyebrow', 'Studio')
@section('heading', 'Contacts')
@section('subheading', 'Everyone we have an email for, in one list.')
@section('content')
    @php
        $typeStyles = [
            'prospect' => 'bg-amber-100 text-amber-900',
            'couple' => 'bg-emerald-100 text-emerald-900',
            'past_couple' => 'bg-emerald-100 text-emerald-900',
        ];
        $active = collect($filters)->filter(fn ($v) => $v !== null && $v !== '')->count();
    @endphp

    <div class="[display:grid] grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-[#6c5743]/20 bg-white/80 p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-[#6d6256]">Contacts</p>
            <p class="mt-1 text-3xl font-semibold text-[#201714]">{{ number_format($totals['contacts']) }}</p>
        </div>
        <div class="rounded-2xl border border-[#6c5743]/20 bg-white/80 p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-[#6d6256]">Do not contact</p>
            <p class="mt-1 text-3xl font-semibold text-rose-800">{{ number_format($totals['suppressed']) }}</p>
        </div>
        <div class="col-span-2 rounded-2xl border border-[#6c5743]/20 bg-white/80 p-4 shadow-sm lg:col-span-1">
            <p class="text-xs font-medium uppercase tracking-wide text-[#6d6256]">Showing</p>
            <p class="mt-1 text-3xl font-semibold text-[#201714]">{{ number_format($contacts->total()) }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.contacts.index') }}"
          class="mt-4 max-w-none! rounded-2xl border border-[#6c5743]/20 bg-white/80 p-4 shadow-sm">
        <div class="[display:grid] grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <label class="flex flex-col gap-1 text-xs font-medium text-[#6d6256] sm:col-span-2 lg:col-span-1">
                Search
                <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Name or email"
                       class="rounded-lg border border-[#6c5743]/30 bg-white px-3 py-2 text-sm text-[#201714]">
            </label>
            <label class="flex flex-col gap-1 text-xs font-medium text-[#6d6256]">
                Type
                <select name="type" class="rounded-lg border border-[#6c5743]/30 bg-white px-3 py-2 text-sm text-[#201714]">
                    <option value="">All</option>
                    @foreach (\App\Models\Contact::TYPES as $key => $label)
                        <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex flex-col gap-1 text-xs font-medium text-[#6d6256]">
                Stage
                <select name="stage" class="rounded-lg border border-[#6c5743]/30 bg-white px-3 py-2 text-sm text-[#201714]">
                    <option value="">All</option>
                    @foreach (\App\Models\Contact::STAGES as $key => $label)
                        <option value="{{ $key }}" @selected($filters['stage'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex flex-col gap-1 text-xs font-medium text-[#6d6256]">
                Source
                <select name="source" class="rounded-lg border border-[#6c5743]/30 bg-white px-3 py-2 text-sm text-[#201714]">
                    <option value="">All</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source }}" @selected($filters['source'] === $source)>{{ $source }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex flex-col gap-1 text-xs font-medium text-[#6d6256]">
                Do not contact
                <select name="dnc" class="rounded-lg border border-[#6c5743]/30 bg-white px-3 py-2 text-sm text-[#201714]">
                    <option value="">Any</option>
                    <option value="1" @selected($filters['dnc'] === '1')>Only DNC</option>
                    <option value="0" @selected($filters['dnc'] === '0')>Not DNC</option>
                </select>
            </label>
        </div>
        <div class="mt-3 flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-[#2d1d15] px-4 py-2 text-sm font-medium text-white">Apply</button>
            @if ($active)
                <a href="{{ route('admin.contacts.index') }}" class="text-sm text-[#6d6256] underline">Clear filters</a>
            @endif
        </div>
    </form>

    <div class="mt-4 overflow-hidden rounded-2xl border border-[#6c5743]/20 bg-white/80 shadow-sm">
        <div class="hidden grid-cols-[2fr_1fr_1fr_1fr_1fr] gap-4 border-b border-[#6c5743]/15 bg-[#f7f0e6] px-4 py-3 text-xs font-semibold uppercase tracking-wide text-[#6d6256] md:grid">
            <span>Contact</span><span>Type</span><span>Stage</span><span>Source</span><span>Last contact</span>
        </div>
        @forelse ($contacts as $contact)
            @php
                $label = $contact->name ?: $contact->email;
                $initial = strtoupper(mb_substr($label, 0, 1));
                $dnc = $suppressed->has($contact->email);
            @endphp
            <div class="relative [display:grid] grid-cols-1 gap-2 border-b border-[#6c5743]/10 px-4 py-3 text-sm last:border-0 hover:bg-[#f7f0e6]/70 md:grid-cols-[2fr_1fr_1fr_1fr_1fr] md:items-center md:gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#2d1d15] text-sm font-semibold text-white">{{ $initial }}</span>
                    <div class="min-w-0">
                        <a href="{{ route('admin.contacts.show', $contact) }}" class="block truncate font-semibold text-[#201714] after:absolute after:inset-0">{{ $label }}</a>
                        @if ($contact->name)
                            <p class="truncate text-xs text-[#6d6256]">{{ $contact->email }}</p>
                        @endif
                    </div>
                    @if ($dnc)
                        <span class="shrink-0 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800">DNC</span>
                    @endif
                </div>
                <div><span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $typeStyles[$contact->type] ?? 'bg-stone-100 text-stone-800' }}">{{ \App\Models\Contact::TYPES[$contact->type] ?? $contact->type }}</span></div>
                <div class="text-[#201714]"><span class="text-xs text-[#6d6256] md:hidden">Stage: </span>{{ \App\Models\Contact::STAGES[$contact->stage] ?? $contact->stage }}</div>
                <div class="truncate text-[#6d6256]"><span class="text-xs md:hidden">Source: </span>{{ $contact->source }}</div>
                <div class="text-[#6d6256]"><span class="text-xs md:hidden">Last contact: </span>{{ optional($contact->last_contact_at)->format('M j, Y') ?: '—' }}</div>
            </div>
        @empty
            <p class="px-4 py-10 text-center text-sm text-[#6d6256]">No contacts match.</p>
        @endforelse
    </div>

    @if ($contacts->hasPages())
        <div class="mt-4">{{ $contacts->links() }}</div>
    @endif
@endsection
