<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Gallery;
use App\Models\ShareToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GalleryShareLinkController extends Controller
{
    public function store(Request $request, Gallery $gallery): RedirectResponse
    {
        $validated = $request->validate([
            'album_id' => ['nullable', Rule::exists('albums', 'id')->where('gallery_id', $gallery->id)],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        $shareable = $validated['album_id'] ?? null
            ? Album::query()->findOrFail($validated['album_id'])
            : $gallery;

        $shareToken = new ShareToken([
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        if (! empty($validated['password'])) {
            $shareToken->password = $validated['password'];
        }

        $shareToken->shareable()->associate($shareable);
        $shareToken->save();

        return back()->with('status', 'Share link created.');
    }

    public function destroy(Gallery $gallery, ShareToken $shareToken): RedirectResponse
    {
        abort_unless($this->belongsToGallery($gallery, $shareToken), 404);

        $shareToken->delete();

        return back()->with('status', 'Share link revoked.');
    }

    private function belongsToGallery(Gallery $gallery, ShareToken $shareToken): bool
    {
        if ($shareToken->shareable_type === Gallery::class) {
            return (int) $shareToken->shareable_id === $gallery->id;
        }

        if ($shareToken->shareable_type === Album::class) {
            return Album::query()->whereKey($shareToken->shareable_id)->where('gallery_id', $gallery->id)->exists();
        }

        return false;
    }
}
