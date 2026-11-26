@extends('layouts.admin-v2')

@section('title', 'Journal Posts')
@section('eyebrow', 'Content')
@section('heading', 'Journal Posts')
@section('subheading', 'Manage the journal archive directly and review imported legacy content before publishing.')
@section('header_actions')
    <x-ui.button :href="route('admin.journal-posts.create')">New Journal Post</x-ui.button>
@endsection
@section('content')

    <x-ui.card flush class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/60">
<tr>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Title</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Published</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Legacy ID</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($posts as $post)
                    <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60">
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $post->title }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $post->post_type }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $post->status }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $post->published_at?->format('M j, Y') ?: '—' }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $post->original_wp_post_id ?: '—' }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300"><a class="text-brand-600 hover:underline dark:text-brand-400" href="{{ route('admin.journal-posts.edit', $post) }}">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.card>

    @if ($posts->hasPages())
        <div class="mt-4">
            {{ $posts->links() }}
        </div>
    @endif
@endsection
