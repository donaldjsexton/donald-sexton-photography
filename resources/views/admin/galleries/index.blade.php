@extends('layouts.admin-v2')

@section('title', 'Galleries')
@section('eyebrow', 'Content')
@section('heading', 'Client Galleries')
@section('subheading', 'Deliver proofing and final galleries to clients with shareable, optionally password-protected links.')
@section('header_actions')
    <x-ui.button :href="route('admin.galleries.create')">New Gallery</x-ui.button>
@endsection
@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
    @endphp
    <form method="GET" action="{{ route('admin.galleries.index') }}" class="max-w-none!">
        <x-ui.card>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <x-ui.field label="Search galleries" class="flex-1">
                    <input class="{{ $input }}" type="search" name="search" value="{{ $search }}" placeholder="Title or slug">
                </x-ui.field>
                <x-ui.button type="submit">Apply</x-ui.button>
                @if ($search !== '')
                    <a class="text-sm text-gray-500 underline dark:text-gray-400" href="{{ route('admin.galleries.index') }}">Clear</a>
                @endif
            </div>
        </x-ui.card>
    </form>

    <x-ui.card flush class="mt-6 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/60">
                <tr>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Title</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Visibility</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Albums</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Created</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($galleries as $gallery)
                    <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60">
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">
                            <div class="min-w-0">
                                <strong class="block font-medium text-gray-900 dark:text-white">{{ $gallery->title }}</strong>
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $gallery->slug }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ ucfirst($gallery->visibility) }}{{ $gallery->password ? ' · protected' : '' }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $gallery->albums_count }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300"><span class="text-xs text-gray-500 dark:text-gray-400">{{ $gallery->created_at?->format('M j, Y') }}</span></td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">
                            <div class="flex items-center justify-end gap-2">
                                <x-ui.button variant="secondary" :href="route('admin.galleries.edit', $gallery)">Manage</x-ui.button>
                                <form method="POST" action="{{ route('admin.galleries.destroy', $gallery) }}" class="max-w-none!"
                                      onsubmit="return confirm('Delete this gallery and its photos?');">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button variant="danger" type="submit">Delete</x-ui.button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No galleries yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    @if ($galleries->hasPages())
        <div class="mt-4">{{ $galleries->links() }}</div>
    @endif
@endsection
