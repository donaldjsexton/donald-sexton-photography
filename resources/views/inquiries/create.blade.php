@extends('layouts.app')

@section('title', 'Check Availability')

@php
    $prefill = $prefill ?? ['primary_name' => '', 'email' => '', 'event_date' => ''];
    $trustQuote = ($featuredTestimonials ?? collect())->first();
@endphp

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
        copy="Share your date and a few details. A short note is enough — most inquiries get a reply within 24 hours."
        shell="tight"
    />

    <section class="section">
        <div class="page-shell--wide page-form-layout">
            <div class="page-form-aside">
                <p class="eyebrow">What To Share</p>
                <p class="section-copy">Your date, venue, and what matters most are enough to start.</p>
                <p class="meta">You do not need every detail yet. We can fill in the rest later.</p>

                <ul class="inquiry-promise">
                    <li><span aria-hidden="true">✓</span> A reply within 24 hours</li>
                    <li><span aria-hidden="true">✓</span> A real person, every time</li>
                    <li><span aria-hidden="true">✓</span> No pressure, no upsells</li>
                </ul>

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

                <ol class="form-stepper" data-form-stepper aria-label="Inquiry progress" hidden>
                    <li class="form-stepper__step" data-step-indicator="1"><span>1</span> Your day</li>
                    <li class="form-stepper__step" data-step-indicator="2"><span>2</span> Your event</li>
                    <li class="form-stepper__step" data-step-indicator="3"><span>3</span> A short note</li>
                </ol>

                <form method="POST" action="{{ route('inquiry.store') }}" data-multistep-form novalidate>
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

                    <div class="form-step" data-step="1">
                        <p class="form-step__eyebrow">Step 1 of 3</p>
                        <h2 class="form-step__title">Let's start with the basics.</h2>
                        <p class="form-step__copy">Just your name, email, and the date you're hoping for.</p>

                        <div class="field-grid">
                            <label>
                                Your name
                                <input type="text" name="primary_name" id="primary_name" value="{{ old('primary_name', $prefill['primary_name']) }}" required autocomplete="name" @error('primary_name') aria-invalid="true" aria-describedby="primary_name-error" @enderror>
                                @error('primary_name')
                                    <p class="field-error" id="primary_name-error">{{ $message }}</p>
                                @enderror
                            </label>
                            <label>
                                Email
                                <input type="email" name="email" id="email" value="{{ old('email', $prefill['email']) }}" required autocomplete="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                                @error('email')
                                    <p class="field-error" id="email-error">{{ $message }}</p>
                                @enderror
                            </label>
                        </div>

                        <div class="field-grid">
                            <label>
                                Date <span class="meta">(or your best guess)</span>
                                <input type="date" name="event_date" id="event_date" value="{{ old('event_date', $prefill['event_date']) }}" @error('event_date') aria-invalid="true" aria-describedby="event_date-error" @enderror>
                                @error('event_date')
                                    <p class="field-error" id="event_date-error">{{ $message }}</p>
                                @enderror
                            </label>
                        </div>
                    </div>

                    <div class="form-step" data-step="2">
                        <p class="form-step__eyebrow">Step 2 of 3</p>
                        <h2 class="form-step__title">A few details about the day.</h2>
                        <p class="form-step__copy">Skip anything you're not sure of yet.</p>

                        <div class="field-grid">
                            <label>
                                What are you planning?
                                <input type="text" name="event_type" id="event_type" value="{{ old('event_type', 'wedding') }}" required @error('event_type') aria-invalid="true" aria-describedby="event_type-error" @enderror>
                                @error('event_type')
                                    <p class="field-error" id="event_type-error">{{ $message }}</p>
                                @enderror
                            </label>
                            <label>
                                Phone <span class="meta">(optional)</span>
                                <input type="text" name="phone" id="phone" value="{{ old('phone') }}" autocomplete="tel" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                                @error('phone')
                                    <p class="field-error" id="phone-error">{{ $message }}</p>
                                @enderror
                            </label>
                        </div>

                        <div class="field-grid">
                            <label>
                                Venue <span class="meta">(if you have one in mind)</span>
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
                    </div>

                    <div class="form-step" data-step="3">
                        <p class="form-step__eyebrow">Step 3 of 3</p>
                        <h2 class="form-step__title">Anything you'd like me to know?</h2>
                        <p class="form-step__copy">A sentence or two is plenty.</p>

                        <label>
                            Message <span class="meta">(optional)</span>
                            <textarea name="message" id="message" rows="6" placeholder="What matters most about the photos? How did you find me? Anything else I should know?" @error('message') aria-invalid="true" aria-describedby="message-error" @enderror>{{ old('message') }}</textarea>
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

                        @if ($trustQuote)
                            <figure class="form-trust-card" data-form-trust>
                                <blockquote>“{{ $trustQuote->quote }}”</blockquote>
                                <figcaption>— {{ $trustQuote->author_name }}{{ $trustQuote->author_context ? ', '.$trustQuote->author_context : '' }}</figcaption>
                            </figure>
                        @endif
                    </div>

                    <div class="form-step__actions" data-form-actions>
                        <button class="cta-secondary form-step__back" type="button" data-form-back hidden>Back</button>
                        <button class="cta form-step__next" type="button" data-form-next hidden>Next</button>
                        <button class="cta form-step__submit" type="submit" data-form-submit>Send Inquiry</button>
                    </div>

                    <p class="form-reassurance meta">A real reply within 24 hours. No automated follow-ups.</p>
                </form>
            </div>
        </div>
    </section>
@endsection
