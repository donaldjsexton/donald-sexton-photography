<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GalleryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $galleries = Gallery::query()
            ->withCount('albums')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $query->where(function ($q) use ($like) {
                    $q->where('title', 'like', $like)->orWhere('slug', 'like', $like);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(24)
            ->withQueryString();

        return view('admin.galleries.index', [
            'galleries' => $galleries,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.galleries.create', [
            'gallery' => new Gallery(['visibility' => Gallery::VISIBILITY_PRIVATE]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateGallery($request);

        $gallery = new Gallery;
        $gallery->title = $validated['title'];
        $gallery->visibility = $validated['visibility'];

        if (! empty($validated['password'])) {
            $gallery->password = $validated['password'];
        }

        $gallery->save();

        return redirect()
            ->route('admin.galleries.edit', $gallery)
            ->with('status', 'Gallery created.');
    }

    public function edit(Gallery $gallery): View
    {
        $gallery->load([
            'albums' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
            'albums.photos',
            'shareTokens.shareable',
        ]);

        return view('admin.galleries.edit', [
            'gallery' => $gallery,
        ]);
    }

    public function update(Request $request, Gallery $gallery): RedirectResponse
    {
        $validated = $this->validateGallery($request, $gallery);

        $gallery->title = $validated['title'];
        $gallery->visibility = $validated['visibility'];

        if (! empty($validated['password'])) {
            $gallery->password = $validated['password'];
        } elseif ($request->boolean('remove_password')) {
            $gallery->password = null;
        }

        $gallery->save();

        return redirect()
            ->route('admin.galleries.edit', $gallery)
            ->with('status', 'Gallery updated.');
    }

    public function setCover(Gallery $gallery, Photo $photo): RedirectResponse
    {
        abort_unless($gallery->orderedPhotos()->contains($photo), 404);

        $gallery->forceFill(['cover_photo_id' => $photo->id])->save();

        return redirect()
            ->route('admin.galleries.edit', $gallery)
            ->with('status', 'Cover photo updated.');
    }

    public function destroy(Gallery $gallery): RedirectResponse
    {
        $photos = $gallery->orderedPhotos();

        $gallery->delete();

        // Remove photos that are no longer referenced by any album.
        foreach ($photos as $photo) {
            if (! $photo->albums()->exists()) {
                $photo->delete();
            }
        }

        return redirect()
            ->route('admin.galleries.index')
            ->with('status', 'Gallery deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateGallery(Request $request, ?Gallery $gallery = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'visibility' => ['required', Rule::in([Gallery::VISIBILITY_PRIVATE, Gallery::VISIBILITY_PUBLIC])],
            'password' => ['nullable', 'string', 'max:255'],
            'remove_password' => ['nullable', 'boolean'],
        ]);
    }
}
