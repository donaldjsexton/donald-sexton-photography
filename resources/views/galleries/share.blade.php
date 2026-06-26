<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ config('app.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Helvetica Neue', Arial, sans-serif; color: #2d1d15; background: #f6efe6; }
        .shell { max-width: 1200px; margin: 0 auto; padding: 24px 16px 64px; }
        .topbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 24px; }
        .topbar h1 { margin: 0; font-size: clamp(20px, 4vw, 30px); letter-spacing: 0.03em; }
        .count { color: #6b5446; font-size: 13px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 18px; border-radius: 999px; background: #2d1d15; color: #fff; text-decoration: none; font-size: 14px; font-weight: 600; border: none; cursor: pointer; }
        .btn:hover { background: #43291d; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; }
        @media (min-width: 640px) { .grid { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; } }
        .tile { position: relative; aspect-ratio: 3 / 2; border-radius: 10px; overflow: hidden; background: #e7d8c5; }
        .tile img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .tile a.download { position: absolute; right: 8px; bottom: 8px; width: 44px; height: 44px; border-radius: 999px; background: rgba(45, 29, 21, 0.78); color: #fff; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 18px; }
        .empty { padding: 48px 0; text-align: center; color: #6b5446; }
    </style>
</head>
<body>
    <div class="shell">
        <div class="topbar">
            <div>
                <h1>{{ $title }}</h1>
                <div class="count">{{ trans_choice(':count photo|:count photos', $photos->count(), ['count' => $photos->count()]) }}</div>
            </div>
            @if ($photos->isNotEmpty())
                <a class="btn" href="{{ route('galleries.share.download', $token) }}">Download all</a>
            @endif
        </div>

        @if ($photos->isEmpty())
            <p class="empty">No photos have been added to this gallery yet.</p>
        @else
            <div class="grid">
                @foreach ($photos as $photo)
                    <div class="tile">
                        <img src="{{ route('galleries.share.photo', ['token' => $token, 'photo' => $photo->uuid]) }}"
                             alt="{{ $photo->original_name }}" loading="lazy">
                        <a class="download" title="Download"
                           href="{{ route('galleries.share.photo.download', ['token' => $token, 'photo' => $photo->uuid]) }}">&#8595;</a>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>
