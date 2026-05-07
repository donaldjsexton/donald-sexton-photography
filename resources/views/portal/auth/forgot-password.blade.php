@extends('portal.layouts.app')

@section('title', 'Forgot Password')

@section('layout')
    <div class="auth-shell">
        <div class="auth-card">
            <div class="brand">
                <p>{{ config('payments.business.name') }}</p>
                <h1>Reset your password</h1>
            </div>

            <div class="card">
                @if (session('status'))
                    <div class="flash">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <ul class="errors" style="list-style: none;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif

                <p class="meta" style="margin:0 0 18px;">
                    Enter the email on your account and we&rsquo;ll send a reset link.
                </p>

                <form method="POST" action="{{ route('portal.password.email') }}">
                    @csrf

                    <label class="field">
                        <span>Email</span>
                        <input type="email" name="email" required autocomplete="email" value="{{ old('email') }}">
                    </label>

                    <button class="btn btn-primary" type="submit" style="width:100%;">Send Reset Link</button>
                </form>

                <p class="meta" style="margin-top:18px; text-align:center;">
                    <a href="{{ route('portal.login') }}">Back to sign in</a>
                </p>
            </div>
        </div>
    </div>
@endsection
