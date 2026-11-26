@extends('layouts.admin-v2')

@section('title', 'Contract Templates')
@section('eyebrow', 'Studio')
@section('heading', 'Contract Templates')
@section('subheading', 'Reusable contract bodies. Use merge variables to auto-fill names, dates, and totals.')
@section('header_actions')
    <div class="flex flex-wrap gap-2">
        <x-ui.button variant="secondary" :href="route('admin.contracts.index')">Back to Contracts</x-ui.button>
        <x-ui.button :href="route('admin.contract-templates.create')">New Template</x-ui.button>
    </div>
@endsection
@section('content')
    <x-ui.card flush class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/60">
<tr>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Name</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Title</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Default?</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($templates as $template)
                    <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60">
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300"><strong class="font-medium text-gray-900 dark:text-white">{{ $template->name }}</strong></td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $template->title }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">{{ $template->is_default ? 'Yes' : '—' }}</td>
                        <td class="px-5 py-3 align-top text-gray-700 dark:text-gray-300">
<a class="text-brand-600 hover:underline dark:text-brand-400" href="{{ route('admin.contract-templates.edit', $template) }}">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/60">
                        <td colspan="4" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No templates yet. Create one to speed up new contracts.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>
@endsection
