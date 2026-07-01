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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use League\Flysystem\FilesystemException;

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
        $failed = 0;

        foreach ($request->file('photos', []) as $file) {
            $result = $this->ingestion->ingest($file->getRealPath(), $album, $file->getClientOriginalName());

            match (true) {
                $result->isCreated() => $created++,
                $result->isDuplicate() => $duplicates++,
                default => $failed++,
            };
        }

        return back()->with('status', $this->uploadSummary($created, $duplicates, $failed));
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
        $files = [];

        foreach ($request->file('photos', []) as $file) {
            // The local disk uses throw=false so a failed write returns false,
            // but directory creation can still throw; either way the batch must
            // stop with a clear error instead of queueing jobs for phantom paths.
            try {
                $path = $file->store('gallery-uploads/'.$batchId, 'local');
            } catch (FilesystemException $e) {
                report($e);
                $path = false;
            }

            if ($path === false || $path === '') {
                $this->discardStagedUploads($files);

                Log::error('Gallery upload staging failed: the "local" disk is not writable. Check ownership and permissions on storage/app/private.', [
                    'album_id' => $album->id,
                ]);

                return response()->json([
                    'message' => __('Upload failed: the server could not write to its storage directory. Ask your administrator to check storage permissions.'),
                ], 500);
            }

            $files[] = [
                'path' => (string) $path,
                'original_name' => $file->getClientOriginalName(),
            ];
        }

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

    /**
     * @param  list<array{path:string, original_name:string}>  $files
     */
    private function discardStagedUploads(array $files): void
    {
        foreach ($files as $file) {
            Storage::disk('local')->delete($file['path']);
        }
    }

    private function uploadSummary(int $created, int $duplicates, int $failed = 0): string
    {
        $summary = trans_choice(':count photo added|:count photos added', $created, ['count' => $created]);

        if ($duplicates > 0) {
            $summary .= ', '.trans_choice(':count duplicate skipped|:count duplicates skipped', $duplicates, ['count' => $duplicates]);
        }

        if ($failed > 0) {
            $summary .= ', '.trans_choice(':count failed|:count failed', $failed, ['count' => $failed]);
        }

        return $summary.'.';
    }
}
