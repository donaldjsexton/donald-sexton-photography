<?php

namespace App\Services\Media;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Single place where an uploaded image becomes a Media row: it writes the
 * file to the public disk, records the intrinsic dimensions, and runs the
 * optimization pipeline. Shared by the media library forms and the batch
 * upload endpoint the picker posts to.
 */
class MediaUploader
{
    public function __construct(private readonly MediaOptimizer $optimizer) {}

    /**
     * Store an uploaded image as a new Media row.
     *
     * @param  array<string,mixed>  $attributes  extra column values (alt_text, credit, …)
     */
    public function store(UploadedFile $file, array $attributes = []): Media
    {
        $media = new Media;

        foreach ($attributes as $column => $value) {
            $media->setAttribute($column, $value);
        }

        $this->applyFile($media, $file);
        $media->save();
        $this->optimize($media);

        return $media;
    }

    /**
     * Point an existing Media row at a newly uploaded file. The row is not
     * saved here — callers persist it alongside their own attribute changes.
     */
    public function applyFile(Media $media, UploadedFile $file): void
    {
        $path = $file->store('media/'.now()->format('Y/m'), 'public');
        [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];

        $media->disk = 'public';
        $media->path = $path;
        $media->filename = $file->getClientOriginalName();
        $media->mime_type = $file->getMimeType();
        $media->width = $width;
        $media->height = $height;
    }

    /**
     * Run the optimization pipeline for a saved upload. Best-effort: the
     * original file is already on disk and the row already exists, so a
     * failure here is logged for a later `media:optimize` run rather than
     * failing the upload.
     */
    public function optimize(Media $media): void
    {
        try {
            $this->optimizer->optimizeUpload($media);
        } catch (\Throwable $throwable) {
            Log::warning('Media upload optimization failed.', [
                'media_id' => $media->id,
                'path' => $media->path,
                'exception' => $throwable->getMessage(),
            ]);
        }
    }
}
