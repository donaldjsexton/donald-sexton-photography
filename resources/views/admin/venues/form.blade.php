@extends('layouts.admin-v2')

@section('title', $venue->exists ? 'Edit Venue' : 'New Venue')
@section('eyebrow', 'Content')
@section('heading', $venue->exists ? 'Edit Venue' : 'New Venue')
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
        class="max-w-none! space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-800/50"
    >
        @csrf
        @if ($venue->exists)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Name">
                <input class="{{ $input }}" type="text" name="name" value="{{ old('name', $venue->name) }}" required>
            </x-ui.field>

            <x-ui.field label="Slug">
                <input class="{{ $input }}" type="text" name="slug" value="{{ old('slug', $venue->slug) }}" placeholder="auto-generated from name">
            </x-ui.field>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.field label="City">
                <input class="{{ $input }}" type="text" name="city" value="{{ old('city', $venue->city) }}">
            </x-ui.field>

            <x-ui.field label="State">
                <input class="{{ $input }}" type="text" name="state" value="{{ old('state', $venue->state) }}">
            </x-ui.field>

            <x-ui.field label="Region">
                <input class="{{ $input }}" type="text" name="region" value="{{ old('region', $venue->region) }}">
            </x-ui.field>
        </div>

        <x-ui.field label="Headline">
            <input class="{{ $input }}" type="text" name="headline" value="{{ old('headline', $venue->headline) }}">
        </x-ui.field>

        <x-ui.field label="Summary">
            <textarea class="{{ $input }}" name="summary" rows="3">{{ old('summary', $venue->summary) }}</textarea>
        </x-ui.field>

        <x-ui.field label="Body">
            <textarea class="{{ $input }}" name="body" rows="10">{{ old('body', $venue->body) }}</textarea>
        </x-ui.field>

        <x-ui.media-picker
            name="hero_media_id"
            label="Hero media"
            help-text="Search by filename, alt text, or ID."
            :value="$venue->hero_media_id"
            :media="$venue->heroMedia"
        />

        <x-ui.field label="Website URL">
            <input class="{{ $input }}" type="url" name="website_url" value="{{ old('website_url', $venue->website_url) }}">
        </x-ui.field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Google Places ID">
                <input class="{{ $input }}" type="text" name="google_places_id" value="{{ old('google_places_id', $venue->google_places_id) }}">
            </x-ui.field>

            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="hidden" name="is_featured" value="0">
                <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $venue->is_featured))>
                Featured venue
            </label>
        </div>

        <fieldset class="space-y-4 rounded-2xl border border-gray-200 p-5 dark:border-gray-700">
            <legend class="px-2 text-sm font-semibold text-gray-900 dark:text-white">Venue referrals</legend>
            <p class="text-sm text-gray-500 dark:text-gray-400">Used by the venue-referral ingestion command to identify and credit referral sources.</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Referral contact name">
                    <input class="{{ $input }}" type="text" name="referral_contact_name" value="{{ old('referral_contact_name', $venue->referral_contact_name) }}">
                </x-ui.field>

                <x-ui.field label="Referral emails">
                    <input
                        class="{{ $input }}"
                        type="text"
                        name="referral_emails"
                        value="{{ $referralEmailsValue }}"
                        placeholder="planner@venue.com, events@venue.com"
                    >
                </x-ui.field>
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="hidden" name="referral_requires_approval" value="0">
                <input type="checkbox" name="referral_requires_approval" value="1" @checked(old('referral_requires_approval', $venue->referral_requires_approval))>
                Require my approval before contacting referred couples
            </label>
            <p class="text-sm text-gray-500 dark:text-gray-400">Turn this on for broadcast-style coordinators (e.g. "Are You Available?" blasts). Their leads are ingested but no message is sent until you approve — confirm availability with the venue first.</p>
        </fieldset>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="SEO title">
                <input class="{{ $input }}" type="text" name="seo_title" value="{{ old('seo_title', $venue->seo_title) }}">
            </x-ui.field>

            <x-ui.field label="SEO description">
                <textarea class="{{ $input }}" name="seo_description" rows="3">{{ old('seo_description', $venue->seo_description) }}</textarea>
            </x-ui.field>
        </div>

        <x-ui.field label="FAQs">
            <textarea class="{{ $input }}" name="faqs_text" rows="6" placeholder="Has anyone gotten married here? | Yes — we've covered several weddings here, and the courtyard light around sunset is especially good.
What time of day looks best at this venue? | Late afternoon through golden hour, with sunset around the west lawn.">{{ $faqsText }}</textarea>
            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">One FAQ per line, formatted <code>Question | Answer</code>. These render on the venue page and emit FAQPage schema so Google can surface them in search.</span>
        </x-ui.field>

        <h3 class="pt-4 text-base font-semibold text-gray-900 dark:text-white">Billing &amp; portal</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">Fill these in if you invoice this venue. The billing email + portal password let them log in at /portal to view their invoices.</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Business name">
                <input class="{{ $input }}" type="text" name="business_name" value="{{ old('business_name', $venue->business_name) }}" placeholder="Defaults to venue name">
            </x-ui.field>

            <x-ui.field label="Net payment terms">
                <input class="{{ $input }}" type="text" name="net_payment_terms" maxlength="50" placeholder="e.g. Net 30" value="{{ old('net_payment_terms', $venue->net_payment_terms) }}">
            </x-ui.field>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Billing email">
                <input class="{{ $input }}" type="email" name="billing_email" value="{{ old('billing_email', $venue->billing_email) }}">
            </x-ui.field>

            <x-ui.field label="Billing contact name">
                <input class="{{ $input }}" type="text" name="billing_contact_name" value="{{ old('billing_contact_name', $venue->billing_contact_name) }}">
            </x-ui.field>
        </div>

        <x-ui.field label="Billing address line 1">
            <input class="{{ $input }}" type="text" name="billing_address_line_1" value="{{ old('billing_address_line_1', $venue->billing_address_line_1) }}">
        </x-ui.field>

        <x-ui.field label="Billing address line 2">
            <input class="{{ $input }}" type="text" name="billing_address_line_2" value="{{ old('billing_address_line_2', $venue->billing_address_line_2) }}">
        </x-ui.field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.field label="Billing city">
                <input class="{{ $input }}" type="text" name="billing_city" value="{{ old('billing_city', $venue->billing_city) }}">
            </x-ui.field>

            <x-ui.field label="Billing state">
                <input class="{{ $input }}" type="text" name="billing_state" value="{{ old('billing_state', $venue->billing_state) }}">
            </x-ui.field>

            <x-ui.field label="Billing postal code">
                <input class="{{ $input }}" type="text" name="billing_postal_code" value="{{ old('billing_postal_code', $venue->billing_postal_code) }}">
            </x-ui.field>

            <x-ui.field label="Billing country (2-letter)">
                <input class="{{ $input }}" type="text" name="billing_country" maxlength="2" value="{{ old('billing_country', $venue->billing_country) }}">
            </x-ui.field>
        </div>

        <x-ui.field label="Set / reset portal password">
            <input class="{{ $input }}" type="password" name="portal_password" minlength="8" autocomplete="new-password" placeholder="{{ $venue->password ? 'Leave blank to keep current password' : 'Min 8 characters' }}">
            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ $venue->password ? 'Portal access is enabled.' : 'Set a password to enable portal sign-in for this venue.' }}</span>
        </x-ui.field>

        <div class="flex flex-wrap items-center gap-3">
            <x-ui.button type="submit">Save Venue</x-ui.button>

            @if ($venue->exists)
                <x-ui.button variant="secondary" :href="route('venues.show', $venue->slug)" target="_blank" rel="noopener">View public page</x-ui.button>
                @if ($venue->isBillable())
                    <x-ui.button variant="secondary" :href="route('admin.invoices.create', ['venue_id' => $venue->id])">New invoice for this venue</x-ui.button>
                @endif
            @endif
        </div>
    </form>

    @if ($venue->exists)
        <form
            method="POST"
            action="{{ route('admin.venues.destroy', $venue) }}"
            class="mt-8 max-w-none!"
            onsubmit="return confirm('Delete this venue? Linked stories and journal posts will keep their records but lose the venue association.');"
        >
            @csrf
            @method('DELETE')
            <x-ui.button variant="danger" type="submit">Delete Venue</x-ui.button>
        </form>
    @endif
@endsection
