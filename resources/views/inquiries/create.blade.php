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

                    <div aria-hidden="true" style="position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden;">
                        <label>
                            Website
                            <input type="text" name="website" value="" tabindex="-1" autocomplete="off">
                        </label>
                    </div>

                    <div class="field-grid">
                        <label>
                            Your name
                            <input type="text" name="primary_name" value="{{ old('primary_name') }}" required>
                        </label>
                        <label>
                            Email
                            <input type="email" name="email" value="{{ old('email') }}" required>
                        </label>
                    </div>

                    <div class="field-grid">
                        <label>
                            Phone
                            <input type="text" name="phone" value="{{ old('phone') }}">
                        </label>
                        <label>
                            Date
                            <input type="date" name="event_date" value="{{ old('event_date') }}">
                        </label>
                    </div>

                    <div class="field-grid">
                        <label>
                            What are you planning?
                            <input type="text" name="event_type" value="{{ old('event_type', 'wedding') }}" required>
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
