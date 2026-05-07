@extends('portal.layouts.app')

@section('title', 'Set Password')

@section('layout')
    <div class="auth-shell">
        <div class="auth-card">
            <div class="brand">
                <p>{{ config('payments.business.name') }}</p>
                <h1>Choose a new password</h1>
            </div>

            <div class="card">
                @if ($errors->any())
                    <ul class="errors" style="list-style: none;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ route('portal.password.update') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <label class="field">
                        <span>Email</span>
                        <input type="email" name="email" required autocomplete="email" value="{{ old('email', $email) }}">
                    </label>

                    <label class="field">
                        <span>New password</span>
                        <input type="password" name="password" required autocomplete="new-password" minlength="8">
                    </label>

                    <label class="field">
                        <span>Confirm password</span>
                        <input type="password" name="password_confirmation" required autocomplete="new-password" minlength="8">
                    </label>

                    <button class="btn btn-primary" type="submit" style="width:100%;">Save New Password</button>
                </form>
            </div>
        </div>
    </div>
@endsection
