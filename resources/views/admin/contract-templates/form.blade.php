@extends('layouts.admin-v2')

@section('title', $template->exists ? 'Edit Template' : 'New Contract Template')
@section('eyebrow', 'Studio')
@section('heading', $template->exists ? 'Edit '.$template->name : 'New Contract Template')
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

    <form
        method="POST"
        action="{{ $template->exists ? route('admin.contract-templates.update', $template) : route('admin.contract-templates.store') }}"
        class="max-w-none! space-y-6"
    >
        @csrf
        @if ($template->exists)
            @method('PUT')
        @endif

        <x-ui.card>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Internal name">
                    <input class="{{ $input }}" type="text" name="name" maxlength="255" required value="{{ old('name', $template->name) }}">
                </x-ui.field>

                <x-ui.field label="Contract title (shown on the document)">
                    <input class="{{ $input }}" type="text" name="title" maxlength="255" required value="{{ old('title', $template->title) }}">
                </x-ui.field>

                <x-ui.field label="Description (internal only)" class="sm:col-span-2">
                    <input class="{{ $input }}" type="text" name="description" maxlength="1000" value="{{ old('description', $template->description) }}">
                </x-ui.field>
            </div>

            <label class="mt-4 inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $template->is_default))>
                Use as the default template for new contracts
            </label>
        </x-ui.card>

        <x-ui.card title="Body">
            <div class="space-y-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Use <code>&#123;&#123;variable&#125;&#125;</code> tokens. They get replaced automatically when a contract is created from this template.
                </p>

                <x-ui.field label="Contract body">
                    <textarea class="{{ $input }}" name="body" rows="22" required>{{ old('body', $template->body) }}</textarea>
                </x-ui.field>

                <details class="text-sm text-gray-500 dark:text-gray-400">
                    <summary class="cursor-pointer">Available merge variables</summary>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach ($availableVariables as $key => $description)
                            <li><code>&#123;&#123;{{ $key }}&#125;&#125;</code> — {{ $description }}</li>
                        @endforeach
                    </ul>
                </details>
            </div>
        </x-ui.card>

        <div class="flex items-center gap-3">
            <x-ui.button type="submit">{{ $template->exists ? 'Save Changes' : 'Create Template' }}</x-ui.button>
            <x-ui.button variant="secondary" :href="route('admin.contract-templates.index')">Cancel</x-ui.button>
        </div>
    </form>

    @if ($template->exists)
        <form method="POST" action="{{ route('admin.contract-templates.destroy', $template) }}" onsubmit="return confirm('Delete this template? Contracts already created from it are unaffected.');" class="mt-8 max-w-none!">
            @csrf
            @method('DELETE')
            <x-ui.button variant="danger" type="submit">Delete</x-ui.button>
        </form>
    @endif
@endsection
