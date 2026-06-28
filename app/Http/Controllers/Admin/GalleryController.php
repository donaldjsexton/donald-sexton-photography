<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Gallery;
use App\Models\Photo;
use App\Services\Galleries\PhotoVariant;
use App\Tenancy\CurrentSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'clients' => $this->clientOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateGallery($request);

        $gallery = new Gallery;
        $gallery->title = $validated['title'];
        $gallery->visibility = $validated['visibility'];
        $gallery->client_id = $validated['client_id'] ?? null;
        $gallery->requires_payment = $request->boolean('requires_payment');

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
            'clients' => $this->clientOptions(),
        ]);
    }

    /**
     * @return Collection<int, Client>
     */
    private function clientOptions(): Collection
    {
        return Client::query()->orderBy('last_name')->orderBy('first_name')->get();
    }

    public function update(Request $request, Gallery $gallery): RedirectResponse
    {
        $validated = $this->validateGallery($request, $gallery);

        $gallery->title = $validated['title'];
        $gallery->visibility = $validated['visibility'];
        $gallery->client_id = $validated['client_id'] ?? null;
        $gallery->requires_payment = $request->boolean('requires_payment');

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

    /**
     * Stream a photo's thumbnail through the app. Originals live on a private
     * disk (R2), so the admin grid cannot link to them directly — it would 404.
     * Falls back to the original when the thumb rendition was never generated.
     */
    public function thumbnail(Gallery $gallery, Photo $photo): StreamedResponse
    {
        abort_unless(
            $photo->albums()->where('albums.gallery_id', $gallery->id)->exists(),
            404,
        );

        return Storage::disk($photo->disk ?? 's3')->response($photo->pathForVariant(PhotoVariant::Thumb));
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
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where('site_id', app(CurrentSite::class)->id())],
            'requires_payment' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'max:255'],
            'remove_password' => ['nullable', 'boolean'],
        ]);
    }
}
