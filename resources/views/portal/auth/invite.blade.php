@extends('portal.layouts.app')

@section('title', 'Set up your portal')

@section('layout')
    <div class="auth-shell">
        <div class="auth-card">
            <div class="brand">
                <p>{{ config('payments.business.name') }}</p>
                <h1>Welcome, {{ $client->portalGreeting() }}</h1>
            </div>

            <div class="card">
                <p class="meta" style="margin:0 0 16px;">Choose a password to finish setting up your client portal.</p>

                @if ($errors->any())
                    <ul class="errors" style="list-style: none;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ request()->fullUrl() }}">
                    @csrf

                    <label class="field">
                        <span>New password</span>
                        <input type="password" name="password" required autocomplete="new-password" minlength="8">
                    </label>

                    <label class="field">
                        <span>Confirm password</span>
                        <input type="password" name="password_confirmation" required autocomplete="new-password" minlength="8">
                    </label>

                    <button class="btn btn-primary" type="submit" style="width:100%;">Set Password & Sign In</button>
                </form>
            </div>
        </div>
    </div>
@endsection
