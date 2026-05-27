@extends('portal.layouts.app')

@section('title', 'Profile Settings')

@php
    $selectedChannels = old('communication_preferences', $client->communication_preferences ?? []);
    $socialConsent = (bool) old('social_media_consent', $client->social_media_consent);
@endphp

@section('content')
    <section class="card stack">
        <div>
            <h2>Profile settings</h2>
            <p class="meta" style="margin:0;">Update your contact details and let us know how you'd prefer to hear from us.</p>
        </div>

        <form method="POST" action="{{ route('portal.settings.update') }}">
            @csrf
            @method('PATCH')

            <div class="form-section">
                <h3>Your information</h3>

                <div class="field-grid">
                    <label class="field">
                        <span>First name</span>
                        <input type="text" value="{{ $client->first_name }}" disabled>
                    </label>
                    <label class="field">
                        <span>Last name</span>
                        <input type="text" value="{{ $client->last_name }}" disabled>
                    </label>
                </div>
                <p class="meta" style="margin: -8px 0 16px;">To change your name or email, please contact us directly.</p>

                <div class="field-grid">
                    <label class="field">
                        <span>Email</span>
                        <input type="email" value="{{ $client->email }}" disabled>
                    </label>
                    <label class="field">
                        <span>Phone</span>
                        <input type="tel" name="phone" value="{{ old('phone', $client->phone) }}" autocomplete="tel">
                    </label>
                </div>
            </div>

            <div class="form-section">
                <h3>Partner</h3>
                <p class="meta" style="margin: 0 0 16px;">If you have a partner involved in this booking, share their name so we can address you both correctly.</p>

                <div class="field-grid">
                    <label class="field">
                        <span>Partner first name</span>
                        <input type="text" name="partner_first_name" value="{{ old('partner_first_name', $client->partner_first_name) }}" autocomplete="off">
                    </label>
                    <label class="field">
                        <span>Partner last name</span>
                        <input type="text" name="partner_last_name" value="{{ old('partner_last_name', $client->partner_last_name) }}" autocomplete="off">
                    </label>
                </div>
            </div>

            <div class="form-section">
                <h3>Mailing address</h3>

                <label class="field">
                    <span>Address line 1</span>
                    <input type="text" name="address_line_1" value="{{ old('address_line_1', $client->address_line_1) }}" autocomplete="address-line1">
                </label>

                <label class="field">
                    <span>Address line 2</span>
                    <input type="text" name="address_line_2" value="{{ old('address_line_2', $client->address_line_2) }}" autocomplete="address-line2">
                </label>

                <div class="field-grid field-grid--3">
                    <label class="field">
                        <span>City</span>
                        <input type="text" name="city" value="{{ old('city', $client->city) }}" autocomplete="address-level2">
                    </label>
                    <label class="field">
                        <span>State / Region</span>
                        <input type="text" name="state" value="{{ old('state', $client->state) }}" autocomplete="address-level1">
                    </label>
                    <label class="field">
                        <span>Postal code</span>
                        <input type="text" name="postal_code" value="{{ old('postal_code', $client->postal_code) }}" autocomplete="postal-code">
                    </label>
                </div>

                <label class="field">
                    <span>Country</span>
                    <input type="text" name="country" value="{{ old('country', $client->country ?: 'US') }}" maxlength="2" autocomplete="country" style="max-width: 120px; text-transform: uppercase;">
                </label>
            </div>

            <div class="form-section">
                <h3>Communication preferences</h3>
                <p class="meta" style="margin: 0 0 16px;">How would you like us to reach you about your booking?</p>

                <div class="check-group">
                    @foreach ($communicationChannels as $value => $label)
                        <label class="check">
                            <input
                                type="checkbox"
                                name="communication_preferences[]"
                                value="{{ $value }}"
                                @checked(in_array($value, $selectedChannels, true))
                            >
                            <span class="label">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="form-section">
                <h3>Sharing your photos</h3>

                <div class="check-group">
                    <label class="check">
                        <input type="hidden" name="social_media_consent" value="0">
                        <input type="checkbox" name="social_media_consent" value="1" @checked($socialConsent)>
                        <span class="label">
                            Yes, you may share my photos on social media and your portfolio.
                            <span class="help">Leave unchecked if you'd prefer your images stay private.</span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="form-section" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a href="{{ route('portal.dashboard') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </section>
@endsection
