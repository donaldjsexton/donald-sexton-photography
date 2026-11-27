@extends('layouts.admin-v2-guest')

@section('title', 'Admin Login')

@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
    @endphp
    <x-ui.card class="!p-0">
        <div class="space-y-5 p-2 sm:p-4">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Admin</p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">Sign in to manage the site.</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Use an existing account to edit pages, stories, media, and settings.</p>
            </div>

            @if ($errors->any())
                <ul class="errors space-y-1 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-4">
                @csrf
                <x-ui.field label="Email">
                    <input class="{{ $input }}" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                </x-ui.field>

                <x-ui.field label="Password">
                    <input class="{{ $input }}" type="password" name="password" required autocomplete="current-password">
                </x-ui.field>

                <label class="admin-checkbox flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-900">
                    <span>Keep this session active</span>
                </label>

                <x-ui.button type="submit" class="w-full">Sign In</x-ui.button>
            </form>
        </div>
    </x-ui.card>
@endsection
