@extends('layouts.app')

@section('title', 'Start your site')
@section('meta_description', 'Create your photography site.')

@section('content')
    <section class="section">
        <div class="page-shell--tight page-stack">
            <div data-reveal>
                <p class="eyebrow">Get Started</p>
                <h1 class="section-title">Create your site</h1>
                <p class="section-copy">Pick a name and address. You can change everything later.</p>
            </div>

            @if ($errors->any())
                <ul class="errors">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('onboarding.store') }}" class="admin-form" data-reveal>
                @csrf

                <label>
                    Studio name
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </label>

                <label>
                    What do you do?
                    <select name="vendor_type">
                        @foreach ($vendorOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('vendor_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    Site address
                    <span class="meta">Your site will live at <strong>your-name.{{ config('app.domain') }}</strong></span>
                    <input type="text" name="subdomain" value="{{ old('subdomain') }}" placeholder="your-name" required>
                </label>

                <div class="field-grid">
                    <label>
                        Your name
                        <input type="text" name="admin_name" value="{{ old('admin_name') }}" required>
                    </label>
                    <label>
                        Email
                        <input type="email" name="admin_email" value="{{ old('admin_email') }}" required>
                    </label>
                </div>

                <div class="field-grid">
                    <label>
                        Password
                        <input type="password" name="admin_password" required>
                    </label>
                    <label>
                        Confirm password
                        <input type="password" name="admin_password_confirmation" required>
                    </label>
                </div>

                <button class="cta" type="submit" style="border: 0; cursor: pointer;">Create site</button>
            </form>
        </div>
    </section>
@endsection
