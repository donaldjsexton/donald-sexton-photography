@extends('portal.layouts.app')

@section('title', 'Sign In')

@section('layout')
    <div class="auth-shell">
        <div class="auth-card">
            <div class="brand">
                <p>{{ config('payments.business.name') }}</p>
                <h1>Sign in to your portal</h1>
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

                <form method="POST" action="{{ route('portal.login.store') }}">
                    @csrf

                    <label class="field">
                        <span>Email</span>
                        <input type="email" name="email" required autocomplete="email" value="{{ old('email') }}">
                    </label>

                    <label class="field">
                        <span>Password</span>
                        <input type="password" name="password" required autocomplete="current-password">
                    </label>

                    <label class="field" style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" name="remember" value="1" style="width:auto; min-height:auto;">
                        <span style="margin:0;">Keep me signed in</span>
                    </label>

                    <button class="btn btn-primary" type="submit" style="width:100%;">Sign In</button>
                </form>

                <p class="meta" style="margin-top:18px; text-align:center;">
                    <a href="{{ route('portal.password.request') }}">Forgot your password?</a>
                </p>
            </div>
        </div>
    </div>
@endsection
