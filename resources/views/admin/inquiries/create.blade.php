@extends('layouts.admin')

@section('title', 'New Lead')
@section('eyebrow', 'Leads')
@section('heading', 'New Lead')
@section('subheading', 'Manually add an inquiry received by phone, email, or referral.')
@section('header_actions')
    <a class="cta-secondary" href="{{ route('admin.inquiries.index') }}">Back to Inquiries</a>
@endsection
@section('content')
    <article class="admin-card">
        @if ($errors->any())
            <ul class="errors">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('admin.inquiries.store') }}" class="admin-form">
            @csrf

            <div class="field-grid">
                <label>
                    Primary name
                    <input type="text" name="primary_name" value="{{ old('primary_name') }}" required>
                </label>
                <label>
                    Partner name
                    <input type="text" name="partner_name" value="{{ old('partner_name') }}">
                </label>
            </div>

            <div class="field-grid">
                <label>
                    Email
                    <input type="email" name="email" value="{{ old('email') }}" required>
                </label>
                <label>
                    Phone
                    <input type="text" name="phone" value="{{ old('phone') }}">
                </label>
            </div>

            <div class="field-grid">
                <label>
                    Event type
                    <input type="text" name="event_type" value="{{ old('event_type', 'wedding') }}" required>
                </label>
                <label>
                    Event date
                    <input type="date" name="event_date" value="{{ old('event_date') }}">
                </label>
            </div>

            <div class="field-grid">
                <label>
                    Venue from the list
                    <select name="venue_id">
                        <option value="">Select a venue</option>
                        @foreach ($venues as $venue)
                            <option value="{{ $venue->id }}" @selected(old('venue_id') == $venue->id)>{{ $venue->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Venue name (if not listed)
                    <input type="text" name="venue_name" value="{{ old('venue_name') }}">
                </label>
            </div>

            <div class="field-grid">
                <label>
                    City
                    <input type="text" name="location_city" value="{{ old('location_city') }}">
                </label>
                <label>
                    Guest count
                    <input type="text" name="guest_count_range" value="{{ old('guest_count_range') }}">
                </label>
            </div>

            <div class="field-grid">
                <label>
                    Budget
                    <input type="text" name="budget_range" value="{{ old('budget_range') }}">
                </label>
                <label>
                    Instagram
                    <input type="text" name="instagram_handle" value="{{ old('instagram_handle') }}">
                </label>
            </div>

            <div class="field-grid">
                <label>
                    Heard about us
                    <input type="text" name="heard_about" value="{{ old('heard_about') }}">
                </label>
                <label>
                    Status
                    <select name="status">
                        @foreach ($statusOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('status', 'new') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <label>
                Message / notes
                <textarea name="message" rows="6">{{ old('message') }}</textarea>
            </label>

            <button class="cta" type="submit" style="border:0; cursor:pointer;">Create Lead</button>
        </form>
    </article>
@endsection
