<?php

namespace App\Jobs;

use App\Events\GalleryUploadProgressed;
use App\Models\Album;
use App\Models\Photo;
use App\Models\Site;
use App\Services\Galleries\IngestionResult;
use App\Services\Galleries\PhotoIngestionService;
use App\Tenancy\CurrentSite;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Ingests a single batch of uploaded gallery photos off the request thread,
 * broadcasting a {@see GalleryUploadProgressed} event after each file so the
 * admin sees live progress over websockets instead of waiting on one blocking
 * upload request. Temp files staged on the local disk are removed as they are
 * consumed, whatever the outcome.
 */
class IngestGalleryUpload implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{path:string, original_name:string}>  $files
     */
    public function __construct(
        public int $albumId,
        public string $batchId,
        public array $files,
    ) {}

    public function handle(PhotoIngestionService $ingestion): void
    {
        $album = Album::withoutSiteScope()->find($this->albumId);

        if ($album === null) {
            $this->discardStagedFiles();

            return;
        }

        // Queued jobs carry no request, so the tenant must be set explicitly;
        // otherwise the global site scope falls back to the default site and
        // ingested photos could be stamped with the wrong site_id.
        app(CurrentSite::class)->set(Site::find($album->site_id));

        $total = count($this->files);
        $index = 0;

        foreach ($this->files as $file) {
            $index++;
            $disk = Storage::disk('local');

            try {
                $result = $ingestion->ingest($disk->path($file['path']), $album, $file['original_name']);
            } finally {
                $disk->delete($file['path']);
            }

            GalleryUploadProgressed::dispatch(
                $album->gallery_id,
                $album->id,
                $this->batchId,
                $index,
                $total,
                $result->status->value,
                $this->photoPayload($album->gallery_id, $result),
                $result->reason,
            );
        }
    }

    /**
     * @return array{uuid:string, id:int, thumb_url:string, original_name:?string}|null
     */
    private function photoPayload(int $galleryId, IngestionResult $result): ?array
    {
        $photo = $result->photo;

        if (! $photo instanceof Photo) {
            return null;
        }

        return [
            'uuid' => $photo->uuid,
            'id' => $photo->id,
            'thumb_url' => route('admin.galleries.photos.thumb', [$galleryId, $photo->id]),
            'original_name' => $photo->original_name,
        ];
    }

    private function discardStagedFiles(): void
    {
        foreach ($this->files as $file) {
            Storage::disk('local')->delete($file['path']);
        }
    }
}
