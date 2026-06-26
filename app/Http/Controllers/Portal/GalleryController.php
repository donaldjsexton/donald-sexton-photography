<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Gallery;
use App\Models\Photo;
use App\Services\Galleries\GalleryArchive;
use App\Services\Galleries\PhotoVariant;
use App\Support\Portal;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The signed-in client's own galleries, surfaced inside the portal. Access is
 * scoped to galleries the client owns; full-resolution downloads honour the
 * gallery's opt-in payment gate.
 */
class GalleryController extends Controller
{
    public function index(): View
    {
        $client = $this->client();

        $galleries = $client->galleries()
            ->with('coverPhoto')
            ->withCount('albums')
            ->orderByDesc('created_at')
            ->get();

        return view('portal.galleries.index', [
            'galleries' => $galleries,
        ]);
    }

    public function show(Gallery $gallery): View
    {
        $client = $this->ownedOrFail($gallery);

        $gallery->load([
            'albums' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
            'albums.photos',
        ]);

        return view('portal.galleries.show', [
            'gallery' => $gallery,
            'downloadsLocked' => $gallery->downloadsLocked(),
        ]);
    }

    public function photo(Gallery $gallery, string $photo): StreamedResponse
    {
        $this->ownedOrFail($gallery);
        $model = $this->photoWithin($gallery, $photo);

        return Storage::disk($model->disk ?? 's3')->response($model->pathForVariant(PhotoVariant::Web));
    }

    public function downloadPhoto(Gallery $gallery, string $photo): StreamedResponse
    {
        $this->ownedOrFail($gallery);
        $this->ensureDownloadable($gallery);
        $model = $this->photoWithin($gallery, $photo);

        return Storage::disk($model->disk ?? 's3')->download($model->path, $model->downloadName());
    }

    public function downloadAll(Gallery $gallery, GalleryArchive $archive): BinaryFileResponse
    {
        $this->ownedOrFail($gallery);
        $this->ensureDownloadable($gallery);

        $photos = $gallery->orderedPhotos();
        abort_if($photos->isEmpty(), 404);

        return $archive->download($photos, str($gallery->title)->slug()->value().'.zip');
    }

    private function client(): Client
    {
        $client = Portal::user();

        abort_unless($client instanceof Client, 403);

        return $client;
    }

    private function ownedOrFail(Gallery $gallery): Client
    {
        $client = $this->client();

        abort_unless($gallery->client_id === $client->id, 404);

        return $client;
    }

    private function ensureDownloadable(Gallery $gallery): void
    {
        abort_if($gallery->downloadsLocked(), 403);
    }

    private function photoWithin(Gallery $gallery, string $photoUuid): Photo
    {
        $photo = $gallery->findPhotoByUuid($photoUuid);

        abort_if($photo === null, 404);

        return $photo;
    }
}
