@extends('layouts.app')

@section('title', 'Check Availability')

@push('json_ld')
    @php
        $inquiryFaqSchema = \App\Support\StructuredData::faqPage([
            [
                'question' => 'Where are you based and how far do you travel?',
                'answer' => 'Donald Sexton Photography is based in Clearwater, Florida and photographs weddings throughout Tampa Bay, St. Petersburg, Sarasota, and the rest of Florida. Travel beyond the Tampa Bay area is welcome and quoted with the inquiry.',
            ],
            [
                'question' => 'How much does wedding photography cost?',
                'answer' => 'Coverage is built on hours, with multiple collections covering six, eight, and ten or more hours of wedding day coverage. Add-ons such as a second shooter, engagement session, and rehearsal coverage are available. See the Collections page for current starting prices, or include your details in an inquiry for a tailored quote.',
            ],
            [
                'question' => 'How do I check if my date is available?',
                'answer' => 'Send your wedding date, venue, and a few details through the inquiry form on this page. You will hear back within a couple of business days with availability and next steps.',
            ],
            [
                'question' => 'What is the photography style?',
                'answer' => 'Calm, documentary wedding photography. The day is captured as it unfolds, with relaxed direction during portraits and family photos. The goal is photographs that feel like the day itself rather than posed reproductions.',
            ],
            [
                'question' => 'Do you offer engagement sessions and elopement coverage?',
                'answer' => 'Yes. Engagement sessions can be added to any wedding collection, and elopement coverage is available as a stand-alone option. Mention either in the inquiry message.',
            ],
        ]);
        $inquiryBreadcrumbSchema = \App\Support\StructuredData::breadcrumbList([
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Check Availability', 'url' => route('inquiry.create')],
        ]);
    @endphp
    <script type="application/ld+json">{!! json_encode($inquiryFaqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/ld+json">{!! json_encode($inquiryBreadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

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

                <x-editorial.google-reviews variant="aside" :limit="2" />
            </div>

            <div class="form-panel">
                @if ($errors->any())
                    <div class="errors" role="alert" tabindex="-1" data-error-summary>
                        <p class="errors__title">Please fix the following before sending:</p>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('inquiry.store') }}">
                    @csrf
                    <input type="hidden" name="utm_source" value="{{ old('utm_source', request('utm_source')) }}">
                    <input type="hidden" name="utm_medium" value="{{ old('utm_medium', request('utm_medium')) }}">
                    <input type="hidden" name="utm_campaign" value="{{ old('utm_campaign', request('utm_campaign')) }}">

                    <div aria-hidden="true" style="position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden;">
                        <label>
                            Website
                            <input type="text" name="website" value="" tabindex="-1" autocomplete="off">
                        </label>
                    </div>

                    <div class="field-grid">
                        <label>
                            Your name
                            <input type="text" name="primary_name" id="primary_name" value="{{ old('primary_name') }}" required @error('primary_name') aria-invalid="true" aria-describedby="primary_name-error" @enderror>
                            @error('primary_name')
                                <p class="field-error" id="primary_name-error">{{ $message }}</p>
                            @enderror
                        </label>
                        <label>
                            Email
                            <input type="email" name="email" id="email" value="{{ old('email') }}" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                            @error('email')
                                <p class="field-error" id="email-error">{{ $message }}</p>
                            @enderror
                        </label>
                    </div>

                    <div class="field-grid">
                        <label>
                            Phone
                            <input type="text" name="phone" id="phone" value="{{ old('phone') }}" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                            @error('phone')
                                <p class="field-error" id="phone-error">{{ $message }}</p>
                            @enderror
                        </label>
                        <label>
                            Date
                            <input type="date" name="event_date" id="event_date" value="{{ old('event_date') }}" @error('event_date') aria-invalid="true" aria-describedby="event_date-error" @enderror>
                            @error('event_date')
                                <p class="field-error" id="event_date-error">{{ $message }}</p>
                            @enderror
                        </label>
                    </div>

                    <div class="field-grid">
                        <label>
                            What are you planning?
                            <input type="text" name="event_type" id="event_type" value="{{ old('event_type', 'wedding') }}" required @error('event_type') aria-invalid="true" aria-describedby="event_type-error" @enderror>
                            @error('event_type')
                                <p class="field-error" id="event_type-error">{{ $message }}</p>
                            @enderror
                        </label>
                    </div>

                    <div class="field-grid">
                        <label>
                            Venue
                            <div class="venue-autocomplete" data-venue-autocomplete data-venue-search-url="{{ route('venues.search') }}">
                                <input
                                    type="text"
                                    name="venue_name"
                                    id="venue_name"
                                    value="{{ old('venue_name') }}"
                                    placeholder="Start typing a venue name"
                                    autocomplete="off"
                                    data-venue-input
                                    @if ($errors->has('venue_name') || $errors->has('venue_id')) aria-invalid="true" aria-describedby="venue_name-error" @endif
                                >
                                <input type="hidden" name="venue_id" value="{{ old('venue_id') }}" data-venue-id>
                                <ul class="venue-autocomplete__list" data-venue-list hidden></ul>
                            </div>
                            @if ($errors->has('venue_name') || $errors->has('venue_id'))
                                <p class="field-error" id="venue_name-error">{{ $errors->first('venue_name') ?: $errors->first('venue_id') }}</p>
                            @endif
                        </label>
                    </div>

                    <label>
                        Message
                        <textarea name="message" id="message" rows="6" @error('message') aria-invalid="true" aria-describedby="message-error" @enderror>{{ old('message') }}</textarea>
                        @error('message')
                            <p class="field-error" id="message-error">{{ $message }}</p>
                        @enderror
                    </label>

                    <fieldset class="sms-consent-group">
                        <legend class="sms-consent-group__heading">Text message updates <span class="meta">(optional)</span></legend>

                        <label class="checkbox-label">
                            <input type="hidden" name="sms_opt_in_transactional" value="0">
                            <input type="checkbox" name="sms_opt_in_transactional" value="1" {{ old('sms_opt_in_transactional') ? 'checked' : '' }}>
                            <span>I agree to receive appointment reminders, booking confirmations, and session details from Donald Sexton Photography via text message. Message frequency varies. Msg &amp; data rates may apply. Reply STOP to opt out, HELP for help.</span>
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
