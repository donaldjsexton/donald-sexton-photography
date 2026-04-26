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

        <div class="field-grid">
            <label>
                Hero media
                <select name="hero_media_id">
                    <option value="">Select media</option>
                    @foreach ($mediaItems as $media)
                        <option value="{{ $media->id }}" @selected((string) old('hero_media_id', $venue->hero_media_id) === (string) $media->id)>#{{ $media->id }} · {{ $media->filename }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                Website URL
                <input type="url" name="website_url" value="{{ old('website_url', $venue->website_url) }}">
            </label>
        </div>

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

        <div class="admin-form__actions">
            <button class="cta" type="submit" style="border: 0; cursor: pointer;">Save Venue</button>

            @if ($venue->exists)
                <a class="cta-secondary" href="{{ route('venues.show', $venue->slug) }}" target="_blank" rel="noopener">View public page</a>
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
