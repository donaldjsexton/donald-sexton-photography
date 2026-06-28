<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\IngestGalleryUpload;
use App\Models\Album;
use App\Models\Gallery;
use App\Models\Photo;
use App\Services\Galleries\PhotoIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GalleryAlbumController extends Controller
{
    public function __construct(private readonly PhotoIngestionService $ingestion) {}

    public function store(Request $request, Gallery $gallery): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'visibility' => ['required', Rule::in([Album::VISIBILITY_PRIVATE, Album::VISIBILITY_PUBLIC])],
        ]);

        $gallery->albums()->create([
            'name' => $validated['name'],
            'visibility' => $validated['visibility'],
            'sort_order' => (int) $gallery->albums()->max('sort_order') + 1,
        ]);

        return back()->with('status', 'Album added.');
    }

    public function update(Request $request, Gallery $gallery, Album $album): RedirectResponse
    {
        $this->ensureAlbumBelongs($gallery, $album);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'visibility' => ['required', Rule::in([Album::VISIBILITY_PRIVATE, Album::VISIBILITY_PUBLIC])],
        ]);

        $album->update($validated);

        return back()->with('status', 'Album updated.');
    }

    public function destroy(Gallery $gallery, Album $album): RedirectResponse
    {
        $this->ensureAlbumBelongs($gallery, $album);

        $photos = $album->photos()->get();
        $album->delete();

        foreach ($photos as $photo) {
            if (! $photo->albums()->exists()) {
                $photo->delete();
            }
        }

        return back()->with('status', 'Album deleted.');
    }

    public function storePhotos(Request $request, Gallery $gallery, Album $album): RedirectResponse
    {
        $this->ensureAlbumBelongs($gallery, $album);

        $request->validate([
            'photos' => ['required', 'array'],
            'photos.*' => ['file', 'image', 'mimes:jpeg,png,webp', 'max:51200'],
        ]);

        $created = 0;
        $duplicates = 0;

        foreach ($request->file('photos', []) as $file) {
            $result = $this->ingestion->ingest($file->getRealPath(), $album, $file->getClientOriginalName());

            match (true) {
                $result->isCreated() => $created++,
                $result->isDuplicate() => $duplicates++,
                default => null,
            };
        }

        return back()->with('status', $this->uploadSummary($created, $duplicates));
    }

    /**
     * Stage an upload and ingest it off-thread so the browser can track each
     * file over websockets. The raw uploads are written to the local disk, a
     * single batch job ingests them, and {@see IngestGalleryUpload} broadcasts
     * progress per file on the gallery's private channel.
     */
    public function uploadPhotos(Request $request, Gallery $gallery, Album $album): JsonResponse
    {
        $this->ensureAlbumBelongs($gallery, $album);

        $request->validate([
            'photos' => ['required', 'array'],
            'photos.*' => ['file', 'image', 'mimes:jpeg,png,webp', 'max:51200'],
        ]);

        $batchId = (string) Str::uuid();

        $files = collect($request->file('photos', []))
            ->map(fn ($file) => [
                'path' => (string) $file->store('gallery-uploads/'.$batchId, 'local'),
                'original_name' => $file->getClientOriginalName(),
            ])
            ->all();

        IngestGalleryUpload::dispatch($album->id, $batchId, $files);

        return response()->json([
            'batch_id' => $batchId,
            'album_id' => $album->id,
            'total' => count($files),
            'channel' => 'galleries.'.$gallery->id,
            'event' => '.upload.progressed',
        ]);
    }

    public function destroyPhoto(Gallery $gallery, Album $album, Photo $photo): RedirectResponse
    {
        $this->ensureAlbumBelongs($gallery, $album);
        abort_unless($album->photos()->whereKey($photo->getKey())->exists(), 404);

        $album->photos()->detach($photo);

        if (! $photo->albums()->exists()) {
            $photo->delete();
        }

        return back()->with('status', 'Photo removed.');
    }

    private function ensureAlbumBelongs(Gallery $gallery, Album $album): void
    {
        abort_unless($album->gallery_id === $gallery->id, 404);
    }

    private function uploadSummary(int $created, int $duplicates): string
    {
        $summary = trans_choice(':count photo added|:count photos added', $created, ['count' => $created]);

        if ($duplicates > 0) {
            $summary .= ', '.trans_choice(':count duplicate skipped|:count duplicates skipped', $duplicates, ['count' => $duplicates]);
        }

        return $summary.'.';
    }
}
