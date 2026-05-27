@extends('layouts.admin')

@section('title', $venue->exists ? 'Edit Venue' : 'New Venue')
@section('eyebrow', 'Content')
@section('heading', $venue->exists ? 'Edit Venue' : 'New Venue')
@section('content')
    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    @php
        $referralEmailsValue = old(
            'referral_emails',
            is_array($venue->referral_emails) ? implode(', ', $venue->referral_emails) : ''
        );
        $faqsText = old('faqs_text', collect($venue->structuredFaqs())
            ->map(fn (array $item) => $item['question'].' | '.$item['answer'])
            ->implode("\n"));
    @endphp

    <form
        method="POST"
        action="{{ $venue->exists ? route('admin.venues.update', $venue) : route('admin.venues.store') }}"
        class="admin-form"
    >
        @csrf
        @if ($venue->exists)
            @method('PUT')
        @endif

        <div class="field-grid">
            <label>
                Name
                <input type="text" name="name" value="{{ old('name', $venue->name) }}" required>
            </label>

            <label>
                Slug
                <input type="text" name="slug" value="{{ old('slug', $venue->slug) }}" placeholder="auto-generated from name">
            </label>
        </div>

        <div class="field-grid">
            <label>
                City
                <input type="text" name="city" value="{{ old('city', $venue->city) }}">
            </label>

            <label>
                State
                <input type="text" name="state" value="{{ old('state', $venue->state) }}">
            </label>

            <label>
                Region
                <input type="text" name="region" value="{{ old('region', $venue->region) }}">
            </label>
        </div>

        <label>
            Headline
            <input type="text" name="headline" value="{{ old('headline', $venue->headline) }}">
        </label>

        <label>
            Summary
            <textarea name="summary" rows="3">{{ old('summary', $venue->summary) }}</textarea>
        </label>

        <label>
            Body
            <textarea name="body" rows="10">{{ old('body', $venue->body) }}</textarea>
        </label>

        <x-admin.media-picker
            name="hero_media_id"
            label="Hero media"
            help-text="Search by filename, alt text, or ID."
            :value="$venue->hero_media_id"
            :media="$venue->heroMedia"
        />

        <label>
            Website URL
            <input type="url" name="website_url" value="{{ old('website_url', $venue->website_url) }}">
        </label>

        <div class="field-grid">
            <label>
                Google Places ID
                <input type="text" name="google_places_id" value="{{ old('google_places_id', $venue->google_places_id) }}">
            </label>

            <label class="admin-form__checkbox">
                <input type="hidden" name="is_featured" value="0">
                <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $venue->is_featured))>
                Featured venue
            </label>
        </div>

        <fieldset class="admin-form__fieldset">
            <legend>Venue referrals</legend>
            <p class="meta">Used by the venue-referral ingestion command to identify and credit referral sources.</p>

            <div class="field-grid">
                <label>
                    Referral contact name
                    <input type="text" name="referral_contact_name" value="{{ old('referral_contact_name', $venue->referral_contact_name) }}">
                </label>

                <label>
                    Referral emails
                    <input
                        type="text"
                        name="referral_emails"
                        value="{{ $referralEmailsValue }}"
                        placeholder="planner@venue.com, events@venue.com"
                    >
                </label>
            </div>
        </fieldset>

        <div class="field-grid">
            <label>
                SEO title
                <input type="text" name="seo_title" value="{{ old('seo_title', $venue->seo_title) }}">
            </label>

            <label>
                SEO description
                <textarea name="seo_description" rows="3">{{ old('seo_description', $venue->seo_description) }}</textarea>
            </label>
        </div>

        <label>
            FAQs
            <textarea name="faqs_text" rows="6" placeholder="Has anyone gotten married here? | Yes — we've covered several weddings here, and the courtyard light around sunset is especially good.
What time of day looks best at this venue? | Late afternoon through golden hour, with sunset around the west lawn.">{{ $faqsText }}</textarea>
            <span class="meta">One FAQ per line, formatted <code>Question | Answer</code>. These render on the venue page and emit FAQPage schema so Google can surface them in search.</span>
        </label>

        <h3 style="margin-top:2rem;">Billing &amp; portal</h3>
        <p class="meta" style="margin-top:0;">Fill these in if you invoice this venue. The billing email + portal password let them log in at /portal to view their invoices.</p>

        <div class="field-grid">
            <label>
                Business name
                <input type="text" name="business_name" value="{{ old('business_name', $venue->business_name) }}" placeholder="Defaults to venue name">
            </label>

            <label>
                Net payment terms
                <input type="text" name="net_payment_terms" maxlength="50" placeholder="e.g. Net 30" value="{{ old('net_payment_terms', $venue->net_payment_terms) }}">
            </label>
        </div>

        <div class="field-grid">
            <label>
                Billing email
                <input type="email" name="billing_email" value="{{ old('billing_email', $venue->billing_email) }}">
            </label>

            <label>
                Billing contact name
                <input type="text" name="billing_contact_name" value="{{ old('billing_contact_name', $venue->billing_contact_name) }}">
            </label>
        </div>

        <label>
            Billing address line 1
            <input type="text" name="billing_address_line_1" value="{{ old('billing_address_line_1', $venue->billing_address_line_1) }}">
        </label>

        <label>
            Billing address line 2
            <input type="text" name="billing_address_line_2" value="{{ old('billing_address_line_2', $venue->billing_address_line_2) }}">
        </label>

        <div class="field-grid">
            <label>
                Billing city
                <input type="text" name="billing_city" value="{{ old('billing_city', $venue->billing_city) }}">
            </label>

            <label>
                Billing state
                <input type="text" name="billing_state" value="{{ old('billing_state', $venue->billing_state) }}">
            </label>

            <label>
                Billing postal code
                <input type="text" name="billing_postal_code" value="{{ old('billing_postal_code', $venue->billing_postal_code) }}">
            </label>

            <label>
                Billing country (2-letter)
                <input type="text" name="billing_country" maxlength="2" value="{{ old('billing_country', $venue->billing_country) }}">
            </label>
        </div>

        <label>
            Set / reset portal password
            <input type="password" name="portal_password" minlength="8" autocomplete="new-password" placeholder="{{ $venue->password ? 'Leave blank to keep current password' : 'Min 8 characters' }}">
            <span class="meta">{{ $venue->password ? 'Portal access is enabled.' : 'Set a password to enable portal sign-in for this venue.' }}</span>
        </label>

        <div class="admin-form__actions">
            <button class="cta" type="submit" style="border: 0; cursor: pointer;">Save Venue</button>

            @if ($venue->exists)
                <a class="cta-secondary" href="{{ route('venues.show', $venue->slug) }}" target="_blank" rel="noopener">View public page</a>
                @if ($venue->isBillable())
                    <a class="cta-secondary" href="{{ route('admin.invoices.create', ['venue_id' => $venue->id]) }}">New invoice for this venue</a>
                @endif
            @endif
        </div>
    </form>

    @if ($venue->exists)
        <form
            method="POST"
            action="{{ route('admin.venues.destroy', $venue) }}"
            class="admin-form admin-form--danger"
            onsubmit="return confirm('Delete this venue? Linked stories and journal posts will keep their records but lose the venue association.');"
        >
            @csrf
            @method('DELETE')
            <button class="cta-secondary" type="submit" style="border: 0; cursor: pointer;">Delete Venue</button>
        </form>
    @endif
@endsection
