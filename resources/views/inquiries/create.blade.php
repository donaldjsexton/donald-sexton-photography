@extends('layouts.app')

@section('title', 'Check Availability')

@section('content')
    @php
        $hasVenues = $venues->isNotEmpty();
    @endphp

    <x-editorial.page-hero
        eyebrow="Inquiry"
        title="Check availability."
        copy="Share your date and a few details. A short note is enough."
        shell="tight"
    />

    <section class="section">
        <div class="page-shell--wide page-form-layout">
            <div class="page-form-aside">
                <p class="eyebrow">What To Share</p>
                <p class="section-copy">Your date, venue, and what matters most are enough to start.</p>
                <p class="meta">You do not need every detail yet. We can fill in the rest later.</p>
            </div>

            <div class="form-panel">
                @if ($errors->any())
                    <ul class="errors">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ route('inquiry.store') }}">
                    @csrf
                    <input type="hidden" name="utm_source" value="{{ old('utm_source', request('utm_source')) }}">
                    <input type="hidden" name="utm_medium" value="{{ old('utm_medium', request('utm_medium')) }}">
                    <input type="hidden" name="utm_campaign" value="{{ old('utm_campaign', request('utm_campaign')) }}">

                    <div class="field-grid">
                        <label>
                            Your name
                            <input type="text" name="primary_name" value="{{ old('primary_name') }}" required>
                        </label>
                        <label>
                            Partner's name
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
                            What are you planning?
                            <input type="text" name="event_type" value="{{ old('event_type', 'wedding') }}" required>
                        </label>
                        <label>
                            Date
                            <input type="date" name="event_date" value="{{ old('event_date') }}">
                        </label>
                    </div>

                    <div class="field-grid">
                        <label>
                            Venue from the list
                            <select name="venue_id" @disabled(! $hasVenues)>
                                <option value="">{{ $hasVenues ? 'Select a venue' : 'Enter venue below' }}</option>
                                @if ($hasVenues)
                                    @foreach ($venues as $venue)
                                        <option value="{{ $venue->id }}" @selected(old('venue_id') == $venue->id)>{{ $venue->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </label>
                        <label>
                            {{ $hasVenues ? 'Venue name if it is not listed' : 'Venue name' }}
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
                            Photo budget
                            <input type="text" name="budget_range" value="{{ old('budget_range') }}">
                        </label>
                        <label>
                            Instagram
                            <input type="text" name="instagram_handle" value="{{ old('instagram_handle') }}">
                        </label>
                    </div>

                    <label>
                        Message
                        <textarea name="message" rows="6">{{ old('message') }}</textarea>
                    </label>

                    <button class="cta" type="submit" style="border: 0; cursor: pointer;">Send Inquiry</button>
                </form>
            </div>
        </div>
    </section>
@endsection
