@extends('layouts.admin')

@section('title', $client->exists ? 'Edit Client' : 'New Client')
@section('eyebrow', 'Studio')
@section('heading', $client->exists ? 'Edit Client' : 'New Client')
@if ($client->exists)
    @section('subheading', $client->displayName())
@endif
@section('content')
    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form
        method="POST"
        action="{{ $client->exists ? route('admin.clients.update', $client) : route('admin.clients.store') }}"
        class="admin-form"
    >
        @csrf
        @if ($client->exists)
            @method('PUT')
        @endif

        <div class="field-grid">
            <label>
                First name
                <input type="text" name="first_name" value="{{ old('first_name', $client->first_name) }}" required>
            </label>

            <label>
                Last name
                <input type="text" name="last_name" value="{{ old('last_name', $client->last_name) }}">
            </label>
        </div>

        <div class="field-grid">
            <label>
                Partner first name
                <input type="text" name="partner_first_name" value="{{ old('partner_first_name', $client->partner_first_name) }}">
            </label>

            <label>
                Partner last name
                <input type="text" name="partner_last_name" value="{{ old('partner_last_name', $client->partner_last_name) }}">
            </label>
        </div>

        <div class="field-grid">
            <label>
                Email
                <input type="email" name="email" value="{{ old('email', $client->email) }}" required>
            </label>

            <label>
                Phone
                <input type="text" name="phone" value="{{ old('phone', $client->phone) }}">
            </label>
        </div>

        <label>
            Company / venue
            <input type="text" name="company" value="{{ old('company', $client->company) }}">
        </label>

        <label>
            Address line 1
            <input type="text" name="address_line_1" value="{{ old('address_line_1', $client->address_line_1) }}">
        </label>

        <label>
            Address line 2
            <input type="text" name="address_line_2" value="{{ old('address_line_2', $client->address_line_2) }}">
        </label>

        <div class="field-grid">
            <label>
                City
                <input type="text" name="city" value="{{ old('city', $client->city) }}">
            </label>

            <label>
                State
                <input type="text" name="state" value="{{ old('state', $client->state) }}">
            </label>

            <label>
                Postal code
                <input type="text" name="postal_code" value="{{ old('postal_code', $client->postal_code) }}">
            </label>

            <label>
                Country (2-letter)
                <input type="text" name="country" maxlength="2" value="{{ old('country', $client->country ?: 'US') }}">
            </label>
        </div>

        <label>
            Internal notes
            <textarea name="notes" rows="4">{{ old('notes', $client->notes) }}</textarea>
        </label>

        <div class="form-actions">
            <button class="cta" type="submit">{{ $client->exists ? 'Save Changes' : 'Create Client' }}</button>
            @if ($client->exists)
                <a class="cta-secondary" href="{{ route('admin.clients.show', $client) }}">Cancel</a>
            @else
                <a class="cta-secondary" href="{{ route('admin.clients.index') }}">Cancel</a>
            @endif
        </div>
    </form>

    @if ($client->exists)
        <form
            method="POST"
            action="{{ route('admin.clients.destroy', $client) }}"
            class="admin-form admin-form--danger"
            onsubmit="return confirm('Delete this client? Invoices and payments will be removed.');"
            style="margin-top: 2rem;"
        >
            @csrf
            @method('DELETE')
            <button class="cta-secondary" type="submit">Delete client</button>
        </form>
    @endif
@endsection
