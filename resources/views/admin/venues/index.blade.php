@extends('layouts.admin-v2')

@section('title', 'Venues')
@section('eyebrow', 'Content')
@section('heading', 'Venues')
@section('subheading', 'Manage venue records used by inquiries, journal posts, and venue-referral automation.')
@section('header_actions')
    <x-ui.button :href="route('admin.venues.create')">New Venue</x-ui.button>
@endsection
@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
    @endphp
    <form method="GET" action="{{ route('admin.venues.index') }}" class="max-w-none!">
        <x-ui.card>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <label class="flex flex-1 flex-col gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                Search venues
                <input
                    class="{{ $input }}"
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Name, city, state, or region"
                >
            </label>

            <x-ui.button type="submit">Apply</x-ui.button>

            @if ($search !== '')
                <a class="text-sm text-gray-500 underline dark:text-gray-400" href="{{ route('admin.venues.index') }}">Clear</a>
            @endif
        </div>
        </x-ui.card>
    </form>

    <x-ui.card flush class="mt-6 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/60">
<tr>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Name</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Location</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Featured</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Referral contact</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Stories</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Journal</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($venues as $venue)
                    <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60">
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">
                            <div class="min-w-0">
                                <strong class="block font-medium text-gray-900 dark:text-white">{{ $venue->name }}</strong>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $venue->slug }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">
                            {{ collect([$venue->city, $venue->state, $venue->region])->filter()->implode(' · ') ?: '—' }}
                        </td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $venue->is_featured ? 'Yes' : 'No' }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">
                            @if ($venue->referral_contact_name || ! empty($venue->referral_emails))
                                <div class="min-w-0">
                                    <strong class="block font-medium text-gray-900 dark:text-white">{{ $venue->referral_contact_name ?: '—' }}</strong>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">
                                        {{ ! empty($venue->referral_emails) ? implode(', ', $venue->referral_emails) : 'No referral emails' }}
                                    </span>
                                </div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $venue->wedding_stories_count }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $venue->journal_posts_count }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300"><a class="text-brand-600 hover:underline dark:text-brand-400" href="{{ route('admin.venues.edit', $venue) }}">Edit</a></td>
                    </tr>
                @empty
                    <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60">
                        <td colspan="7" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No venues match the current filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    @if ($venues->hasPages())
        <div class="mt-4">
            {{ $venues->links() }}
        </div>
    @endif
@endsection
