@extends('layouts.admin-v2')

@section('title', $client->exists ? 'Edit Client' : 'New Client')
@section('eyebrow', 'Studio')
@section('heading', $client->exists ? 'Edit Client' : 'New Client')
@if ($client->exists)
    @section('subheading', $client->displayName())
@endif
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
        action="{{ $client->exists ? route('admin.clients.update', $client) : route('admin.clients.store') }}"
        class="max-w-3xl space-y-4"
    >
        @csrf
        @if ($client->exists)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="First name">
    <input class="{{ $input }}" type="text" name="first_name" value="{{ old('first_name', $client->first_name) }}" required>
</x-ui.field>

            <x-ui.field label="Last name">
    <input class="{{ $input }}" type="text" name="last_name" value="{{ old('last_name', $client->last_name) }}">
</x-ui.field>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Partner first name">
    <input class="{{ $input }}" type="text" name="partner_first_name" value="{{ old('partner_first_name', $client->partner_first_name) }}">
</x-ui.field>

            <x-ui.field label="Partner last name">
    <input class="{{ $input }}" type="text" name="partner_last_name" value="{{ old('partner_last_name', $client->partner_last_name) }}">
</x-ui.field>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Email">
    <input class="{{ $input }}" type="email" name="email" value="{{ old('email', $client->email) }}" required>
</x-ui.field>

            <x-ui.field label="Phone">
    <input class="{{ $input }}" type="text" name="phone" value="{{ old('phone', $client->phone) }}">
</x-ui.field>
        </div>

        <x-ui.field label="Company / venue">
    <input class="{{ $input }}" type="text" name="company" value="{{ old('company', $client->company) }}">
</x-ui.field>

        <x-ui.field label="Address line 1">
    <input class="{{ $input }}" type="text" name="address_line_1" value="{{ old('address_line_1', $client->address_line_1) }}">
</x-ui.field>

        <x-ui.field label="Address line 2">
    <input class="{{ $input }}" type="text" name="address_line_2" value="{{ old('address_line_2', $client->address_line_2) }}">
</x-ui.field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="City">
    <input class="{{ $input }}" type="text" name="city" value="{{ old('city', $client->city) }}">
</x-ui.field>

            <x-ui.field label="State">
    <input class="{{ $input }}" type="text" name="state" value="{{ old('state', $client->state) }}">
</x-ui.field>

            <x-ui.field label="Postal code">
    <input class="{{ $input }}" type="text" name="postal_code" value="{{ old('postal_code', $client->postal_code) }}">
</x-ui.field>

            <x-ui.field label="Country (2-letter)">
    <input class="{{ $input }}" type="text" name="country" maxlength="2" value="{{ old('country', $client->country ?: 'US') }}">
</x-ui.field>
        </div>

        <x-ui.field label="Internal notes">
    <textarea class="{{ $input }}" name="notes" rows="4">{{ old('notes', $client->notes) }}</textarea>
</x-ui.field>

        <div class="flex items-center gap-3 pt-2">
            <x-ui.button type="submit">{{ $client->exists ? 'Save changes' : 'Create client' }}</x-ui.button>
            @if ($client->exists)
                <x-ui.button variant="secondary" href="{{ route('admin.clients.show', $client) }}">Cancel</x-ui.button>
            @else
                <x-ui.button variant="secondary" href="{{ route('admin.clients.index') }}">Cancel</x-ui.button>
            @endif
        </div>
    </form>

    @if ($client->exists)
        <form
            method="POST"
            action="{{ route('admin.clients.destroy', $client) }}"
            class="mt-8"
            onsubmit="return confirm('Delete this client? Invoices and payments will be removed.');"
                    >
            @csrf
            @method('DELETE')
            <x-ui.button variant="danger" type="submit">Delete client</x-ui.button>
        </form>
    @endif
@endsection
