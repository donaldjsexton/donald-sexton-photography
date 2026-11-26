@extends('layouts.admin-v2')

@section('title', 'New Lead')
@section('eyebrow', 'Leads')
@section('heading', 'New Lead')
@section('subheading', 'Manually add an inquiry received by phone, email, or referral.')
@section('header_actions')
    <x-ui.button variant="secondary" :href="route('admin.inquiries.index')">Back to Inquiries</x-ui.button>
@endsection
@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
    @endphp
    <x-ui.card class="max-w-4xl">
        @if ($errors->any())
            <ul class="mb-4 list-disc rounded-xl border border-rose-200 bg-rose-50 px-8 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('admin.inquiries.store') }}" class="max-w-none! space-y-4">
            @csrf

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Primary name">
                    <input class="{{ $input }}" type="text" name="primary_name" value="{{ old('primary_name') }}" required>
                </x-ui.field>
                <x-ui.field label="Partner name">
                    <input class="{{ $input }}" type="text" name="partner_name" value="{{ old('partner_name') }}">
                </x-ui.field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Email">
                    <input class="{{ $input }}" type="email" name="email" value="{{ old('email') }}" required>
                </x-ui.field>
                <x-ui.field label="Phone">
                    <input class="{{ $input }}" type="text" name="phone" value="{{ old('phone') }}">
                </x-ui.field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Event type">
                    <select class="{{ $input }}" name="event_type" required>
                        <option value="">Select a type</option>
                        @foreach (\App\Models\Inquiry::eventTypeOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(old('event_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Event date">
                    <input class="{{ $input }}" type="date" name="event_date" value="{{ old('event_date') }}">
                </x-ui.field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Venue from the list">
                    <select class="{{ $input }}" name="venue_id">
                        <option value="">Select a venue</option>
                        @foreach ($venues as $venue)
                            <option value="{{ $venue->id }}" @selected(old('venue_id') == $venue->id)>{{ $venue->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Venue name (if not listed)">
                    <input class="{{ $input }}" type="text" name="venue_name" value="{{ old('venue_name') }}">
                </x-ui.field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="City">
                    <input class="{{ $input }}" type="text" name="location_city" value="{{ old('location_city') }}">
                </x-ui.field>
                <x-ui.field label="Guest count">
                    <input class="{{ $input }}" type="text" name="guest_count_range" value="{{ old('guest_count_range') }}">
                </x-ui.field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Budget">
                    <input class="{{ $input }}" type="text" name="budget_range" value="{{ old('budget_range') }}">
                </x-ui.field>
                <x-ui.field label="Instagram">
                    <input class="{{ $input }}" type="text" name="instagram_handle" value="{{ old('instagram_handle') }}">
                </x-ui.field>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Heard about us">
                    <input class="{{ $input }}" type="text" name="heard_about" value="{{ old('heard_about') }}">
                </x-ui.field>
                <x-ui.field label="Status">
                    <select class="{{ $input }}" name="status">
                        @foreach ($statusOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('status', 'new') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>

            <x-ui.field label="Message / notes">
                    <textarea class="{{ $input }}" name="message" rows="6">{{ old('message') }}</textarea>
            </x-ui.field>

            <x-ui.button type="submit">Create Lead</x-ui.button>
        </form>
    </x-ui.card>
@endsection
