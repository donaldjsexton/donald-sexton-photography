@extends('layouts.admin-v2')

@section('title', 'Wedding Stories')
@section('eyebrow', 'Content')
@section('heading', 'Wedding Stories')
@section('subheading', 'Manage featured portfolio stories, story summaries, venues, and publication state.')
@section('header_actions')
    <x-ui.button :href="route('admin.wedding-stories.create')">New Story</x-ui.button>
@endsection
@section('content')

    <x-ui.card flush class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/60">
<tr>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Title</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Venue</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Published</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($stories as $story)
                    <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60">
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $story->title }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $story->story_type }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $story->status }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $story->venue?->name ?: '—' }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $story->published_at?->format('M j, Y') ?: '—' }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300"><a class="text-brand-600 hover:underline dark:text-brand-400" href="{{ route('admin.wedding-stories.edit', $story) }}">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.card>

    @if ($stories->hasPages())
        <div class="mt-4">
            {{ $stories->links() }}
        </div>
    @endif
@endsection

