<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="admin-page admin-page--login">
    <main class="admin-login">
        <div class="form-panel admin-login__panel">
            <p class="eyebrow">Admin</p>
            <h1 class="section-title">Sign in to manage the site.</h1>
            <p class="section-copy">Use an existing account to edit pages, stories, media, and settings.</p>

            @if ($errors->any())
                <ul class="errors">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('admin.login.store') }}">
                @csrf
                <label>
                    Email
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus>
                </label>

                <label>
                    Password
                    <input type="password" name="password" required>
                </label>

                <label class="admin-checkbox">
                    <input type="checkbox" name="remember" value="1">
                    <span>Keep this session active</span>
                </label>

                <button class="cta" type="submit" style="border: 0; cursor: pointer;">Sign In</button>
            </form>
        </div>
    </main>
</body>
</html>
