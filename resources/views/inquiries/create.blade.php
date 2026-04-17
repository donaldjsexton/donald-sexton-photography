@extends('layouts.app')

@section('title', 'Check Availability')

@section('content')
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
                            Venue
                            <div class="venue-autocomplete" data-venue-autocomplete data-venue-search-url="{{ route('venues.search') }}">
                                <input
                                    type="text"
                                    name="venue_name"
                                    value="{{ old('venue_name') }}"
                                    placeholder="Start typing a venue name"
                                    autocomplete="off"
                                    data-venue-input
                                >
                                <input type="hidden" name="venue_id" value="{{ old('venue_id') }}" data-venue-id>
                                <ul class="venue-autocomplete__list" data-venue-list hidden></ul>
                            </div>
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

                    <fieldset class="sms-consent-group">
                        <legend class="sms-consent-group__heading">Text message updates <span class="meta">(optional)</span></legend>

                        <label class="checkbox-label">
                            <input type="hidden" name="sms_opt_in_transactional" value="0">
                            <input type="checkbox" name="sms_opt_in_transactional" value="1" {{ old('sms_opt_in_transactional') ? 'checked' : '' }}>
                            <span>I agree to receive appointment reminders, booking confirmations, and session details from Donald Sexton Photography via text message. Message frequency varies. Msg &amp; data rates may apply. Reply STOP to opt out, HELP for help.</span>
                        </label>

                        <label class="checkbox-label">
                            <input type="hidden" name="sms_opt_in_marketing" value="0">
                            <input type="checkbox" name="sms_opt_in_marketing" value="1" {{ old('sms_opt_in_marketing') ? 'checked' : '' }}>
                            <span>I agree to receive promotional text messages from Donald Sexton Photography, including special offers and mini-session announcements. Message frequency varies. Msg &amp; data rates may apply. Reply STOP to unsubscribe, HELP for help. Consent is not a condition of purchase.</span>
                        </label>

                        <p class="sms-consent-group__links meta">
                            By opting in you agree to our <a href="{{ route('legal.privacy') }}" target="_blank">Privacy Policy</a> and <a href="{{ route('legal.terms') }}" target="_blank">Terms of Service</a>.
                        </p>
                    </fieldset>

                    <button class="cta" type="submit" style="border: 0; cursor: pointer;">Send Inquiry</button>
                </form>
            </div>
        </div>
    </section>
@endsection
