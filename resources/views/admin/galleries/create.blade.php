@extends('layouts.admin-v2')

@section('title', 'New Gallery')
@section('eyebrow', 'Content')
@section('heading', 'New Gallery')
@section('subheading', 'Name the gallery, then add albums and upload photos.')
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

    <form method="POST" action="{{ route('admin.galleries.store') }}"
          class="admin-form max-w-none! space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800/50">
        @csrf

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Title">
                <input class="{{ $input }}" type="text" name="title" value="{{ old('title') }}" required autofocus>
            </x-ui.field>

            <x-ui.field label="Visibility">
                <select class="{{ $input }}" name="visibility">
                    <option value="private" @selected(old('visibility', 'private') === 'private')>Private (link only)</option>
                    <option value="public" @selected(old('visibility') === 'public')>Public</option>
                </select>
            </x-ui.field>

            <x-ui.field label="Client (optional)">
                <select class="{{ $input }}" name="client_id">
                    <option value="">Unassigned</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->displayName() }}</option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Password (optional)">
                <input class="{{ $input }}" type="text" name="password" value="{{ old('password') }}" autocomplete="off"
                       placeholder="Leave blank for no password">
            </x-ui.field>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" name="requires_payment" value="1" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(old('requires_payment'))>
            Require the balance to be paid before full-resolution downloads
        </label>

        <div class="flex flex-wrap gap-3">
            <x-ui.button type="submit">Create gallery</x-ui.button>
            <x-ui.button variant="secondary" :href="route('admin.galleries.index')">Cancel</x-ui.button>
        </div>
    </form>
@endsection
