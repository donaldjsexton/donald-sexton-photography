@extends('layouts.admin-v2')

@section('title', 'Pages')
@section('eyebrow', 'Content')
@section('heading', 'Pages')
@section('subheading', 'Manage about, collections, locations, and supporting evergreen content.')
@section('header_actions')
    <x-ui.button :href="route('admin.pages.create')">New Page</x-ui.button>
@endsection
@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
    @endphp

    @if ($errors->any())
        <ul class="mb-4 list-disc rounded-xl border border-rose-200 bg-rose-50 px-8 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <x-ui.card>
        <p class="text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-400">Location landing page</p>
        <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Draft a "[City] Wedding Photographer" page</h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Scaffolds a draft location page with title, slug, excerpt, body, and SEO meta. The public page will auto-pull matching wedding stories, venues, and journal posts based on city and state.</p>

        <form method="POST" action="{{ route('admin.pages.generate-location') }}" class="mt-4 max-w-none! space-y-4">
            @csrf

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-ui.field label="City *">
                    <input class="{{ $input }}" type="text" name="city" value="{{ old('city') }}" required placeholder="Tampa">
                </x-ui.field>
                <x-ui.field label="State">
                    <input class="{{ $input }}" type="text" name="state" value="{{ old('state', 'FL') }}" placeholder="FL">
                </x-ui.field>
                <x-ui.field label="Region">
                    <input class="{{ $input }}" type="text" name="region" value="{{ old('region') }}" placeholder="Tampa Bay">
                </x-ui.field>
            </div>

            <x-ui.button variant="secondary" type="submit">Generate Draft</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card flush class="mt-6 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/60">
<tr>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Title</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Slug</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Template</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Updated</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pages as $page)
                    <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60">
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $page->title }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $page->slug }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $page->template }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $page->status }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $page->updated_at?->format('M j, Y') }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300"><a class="text-brand-600 hover:underline dark:text-brand-400" href="{{ route('admin.pages.edit', $page) }}">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.card>

    @if ($pages->hasPages())
        <div class="mt-4">
            {{ $pages->links() }}
        </div>
    @endif
@endsection
