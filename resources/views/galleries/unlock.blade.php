<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Protected gallery — {{ config('app.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Helvetica Neue', Arial, sans-serif; color: #2d1d15; background: #f6efe6; padding: 20px; }
        .card { width: 100%; max-width: 380px; background: #fff; border: 1px solid #e7d8c5; border-radius: 14px; padding: 28px; }
        h1 { margin: 0 0 6px; font-size: 20px; }
        p { margin: 0 0 18px; color: #6b5446; font-size: 14px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        input { width: 100%; min-height: 44px; padding: 0 14px; border: 1px solid #d8c6b2; border-radius: 8px; font-size: 16px; }
        .btn { width: 100%; min-height: 44px; margin-top: 16px; border: none; border-radius: 999px; background: #2d1d15; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #43291d; }
        .error { color: #9b2c2c; font-size: 13px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>This gallery is protected</h1>
        <p>Enter the password you were given to view the photos.</p>
        <form method="POST" action="{{ route('galleries.share.unlock', $token) }}">
            @csrf
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" autofocus required>
            @if ($error)
                <div class="error">{{ $error }}</div>
            @endif
            <button class="btn" type="submit">View gallery</button>
        </form>
    </div>
</body>
</html>
