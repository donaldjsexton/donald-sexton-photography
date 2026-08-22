<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Models\JournalPost;
use App\Models\Photo;
use App\Models\WeddingStory;
use App\Services\Galleries\PhotoFormat;
use App\Services\Galleries\PhotoVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves web-sized photos for a gallery embedded in public editorial content
 * (a published wedding story or journal post), or a gallery that is itself
 * public. No token is required because the content is already published.
 */
class GalleryEmbedController extends Controller
{
    /**
     * Stream a rendition in the best format the client advertises. The URL is
     * format-agnostic, so the response must carry Vary: Accept or a shared
     * cache can hand an AVIF body to a client that cannot decode it.
     */
    public function photo(Request $request, Gallery $gallery, string $photo): StreamedResponse
    {
        abort_unless($this->isPubliclyEmbeddable($gallery), 404);

        $model = $this->photoWithin($gallery, $photo);
        $formats = PhotoFormat::negotiate($request->header('Accept'));

        return Storage::disk($model->disk ?? 's3')
            ->response($model->pathForVariant(PhotoVariant::Web, $formats), null, ['Vary' => 'Accept']);
    }

    private function isPubliclyEmbeddable(Gallery $gallery): bool
    {
        if ($gallery->isPublic()) {
            return true;
        }

        $referencedByStory = WeddingStory::query()
            ->published()
            ->where('gallery_id', $gallery->id)
            ->exists();

        $referencedByPost = JournalPost::query()
            ->published()
            ->where('gallery_id', $gallery->id)
            ->exists();

        return $referencedByStory || $referencedByPost;
    }

    private function photoWithin(Gallery $gallery, string $photoUuid): Photo
    {
        $photo = $gallery->findPhotoByUuid($photoUuid);

        abort_if($photo === null, 404);

        return $photo;
    }
}
