<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Models\JournalPost;
use App\Models\Photo;
use App\Models\WeddingStory;
use App\Services\Galleries\PhotoVariant;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves web-sized photos for a gallery embedded in public editorial content
 * (a published wedding story or journal post), or a gallery that is itself
 * public. No token is required because the content is already published.
 */
class GalleryEmbedController extends Controller
{
    public function photo(Gallery $gallery, string $photo): StreamedResponse
    {
        abort_unless($this->isPubliclyEmbeddable($gallery), 404);

        $model = $this->photoWithin($gallery, $photo);

        return Storage::disk($model->disk ?? 's3')->response($model->pathForVariant(PhotoVariant::Web));
    }

    private function isPubliclyEmbeddable(Gallery $gallery): bool
    {
        if ($gallery->isPublic()) {
            return true;
        }

        $referencedByStory = WeddingStory::query()
            ->where('gallery_id', $gallery->id)
            ->where('status', 'published')
            ->exists();

        $referencedByPost = JournalPost::query()
            ->where('gallery_id', $gallery->id)
            ->where('status', 'published')
            ->exists();

        return $referencedByStory || $referencedByPost;
    }

    private function photoWithin(Gallery $gallery, string $photoUuid): Photo
    {
        $photo = $gallery->orderedPhotos()->firstWhere('uuid', $photoUuid);

        abort_if($photo === null, 404);

        return $photo;
    }
}
