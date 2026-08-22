<?php

namespace App\Services\Galleries;

use App\Models\Album;
use App\Models\Photo;
use App\Services\Media\GdImageProcessor;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\FilesystemException;

/**
 * Controlled ingestion pipeline for client-gallery photos. Ports the Java
 * engine's behaviour into Laravel:
 *
 * - hash-first dedup (SHA-256, unique per site) so identical re-uploads are a
 *   no-op that reuses the existing asset rather than expanding storage;
 * - idempotent / restart-safe: re-ingesting an existing photo just ensures it
 *   is attached to the target album;
 * - fail-fast on unreadable / unsupported files, before any persistence;
 * - non-blocking EXIF and variant generation: their failure never aborts the
 *   ingest, the original is always stored and recorded.
 *
 * The original is stored under galleries/{site}/{gallery}/{uuid}.{ext} on the
 * configured disk (R2 in production), with WebP renditions written alongside.
 */
class PhotoIngestionService
{
    private const SUPPORTED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly ?string $disk = null,
        private readonly GdImageProcessor $gd = new GdImageProcessor,
    ) {}

    /**
     * Ingest a single image from a local path into an album.
     */
    public function ingest(string $sourcePath, Album $album, ?string $originalName = null, ?int $sortOrder = null): IngestionResult
    {
        if (! is_file($sourcePath)) {
            return IngestionResult::failed('missing_file');
        }

        $sha256 = hash_file('sha256', $sourcePath);

        if ($sha256 === false) {
            return IngestionResult::failed('unreadable_file');
        }

        // Dedup before storage: the unique (site_id, sha256) key means an
        // identical upload reuses the existing row. The global site scope keeps
        // this lookup tenant-local automatically.
        $existing = Photo::query()->where('sha256', $sha256)->first();

        if ($existing !== null) {
            $this->attach($album, $existing, $sortOrder);

            return IngestionResult::duplicate($existing);
        }

        $imageInfo = @getimagesize($sourcePath);

        if (! is_array($imageInfo)) {
            return IngestionResult::failed('unreadable_image');
        }

        $mime = (string) ($imageInfo['mime'] ?? '');

        if (! in_array($mime, self::SUPPORTED_MIMES, true)) {
            return IngestionResult::failed('unsupported_mime');
        }

        [$width, $height] = $imageInfo;

        $uuid = (string) Str::uuid();
        $extension = $this->extensionFor($mime);
        $path = sprintf('galleries/%d/%d/%s.%s', $album->site_id, $album->gallery_id, $uuid, $extension);

        $stream = fopen($sourcePath, 'rb');

        if ($stream === false) {
            return IngestionResult::failed('unreadable_file');
        }

        // The configured disks use throw=false (returning false on write
        // failure), but directory-creation errors on local disks still throw;
        // both must fail the ingest before a Photo row points at nothing.
        try {
            $written = $this->storage()->writeStream($path, $stream) !== false;
        } catch (FilesystemException $e) {
            Log::error('Gallery ingest could not write the original to the "'.$this->diskName().'" disk: '.$e->getMessage(), [
                'path' => $path,
            ]);

            return IngestionResult::failed('storage_write_failed');
        } finally {
            fclose($stream);
        }

        if (! $written) {
            Log::error('Gallery ingest could not write the original to the "'.$this->diskName().'" disk; check disk credentials and permissions.', [
                'path' => $path,
            ]);

            return IngestionResult::failed('storage_write_failed');
        }

        $exif = $this->extractExif($sourcePath, $mime);

        $photo = Photo::create([
            'uuid' => $uuid,
            'disk' => $this->diskName(),
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => $mime,
            'size_bytes' => filesize($sourcePath) ?: null,
            'sha256' => $sha256,
            'width' => (int) $width,
            'height' => (int) $height,
            'camera' => $exif['camera'] ?? null,
            'lens' => $exif['lens'] ?? null,
            'taken_at' => $exif['taken_at'] ?? null,
            'exif' => $exif['data'] ?? null,
        ]);

        // Best-effort renditions; a failure here leaves the original intact.
        $this->generateVariants($sourcePath, $mime, $path);

        $this->attach($album, $photo, $sortOrder);

        return IngestionResult::created($photo);
    }

    /**
     * Idempotently attach a photo to an album, preserving any existing order.
     */
    private function attach(Album $album, Photo $photo, ?int $sortOrder): void
    {
        if ($album->photos()->whereKey($photo->getKey())->exists()) {
            return;
        }

        $album->photos()->attach($photo, [
            'site_id' => $album->site_id,
            'sort_order' => $sortOrder ?? $this->nextSortOrder($album),
            'added_at' => now(),
        ]);
    }

    private function nextSortOrder(Album $album): int
    {
        return (int) $album->photos()->max('album_photo.sort_order') + 1;
    }

    private function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    private function diskName(): string
    {
        return $this->disk ?? (string) config('galleries.disk', 's3');
    }

    private function storage(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    /**
     * Extract a tidy, JSON-safe EXIF subset. Never throws: any failure yields
     * an empty result so ingestion proceeds.
     *
     * @return array{camera?:?string, lens?:?string, taken_at?:?Carbon, data?:?array<string,mixed>}
     */
    private function extractExif(string $sourcePath, string $mime): array
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return [];
        }

        try {
            $raw = @exif_read_data($sourcePath, null, true);
        } catch (\Throwable) {
            return [];
        }

        if (! is_array($raw)) {
            return [];
        }

        $ifd0 = $raw['IFD0'] ?? [];
        $exif = $raw['EXIF'] ?? [];
        $gps = $raw['GPS'] ?? [];

        $make = isset($ifd0['Make']) ? trim((string) $ifd0['Make']) : '';
        $model = isset($ifd0['Model']) ? trim((string) $ifd0['Model']) : '';
        $camera = trim($make.' '.$model) ?: null;

        $lens = $exif['LensModel'] ?? $exif['UndefinedTag:0xA434'] ?? null;
        $lens = $lens !== null ? trim((string) $lens) : null;

        $takenAt = null;
        $dateTime = $exif['DateTimeOriginal'] ?? $ifd0['DateTime'] ?? null;

        if (is_string($dateTime) && $dateTime !== '') {
            $takenAt = Carbon::createFromFormat('Y:m:d H:i:s', $dateTime) ?: null;
        }

        $data = $this->scalarsOnly([
            'make' => $make ?: null,
            'model' => $model ?: null,
            'lens' => $lens,
            'aperture' => $exif['FNumber'] ?? $exif['ApertureValue'] ?? null,
            'shutter_speed' => $exif['ExposureTime'] ?? null,
            'iso' => $exif['ISOSpeedRatings'] ?? null,
            'focal_length' => $exif['FocalLength'] ?? null,
            'orientation' => $ifd0['Orientation'] ?? null,
            'gps_latitude' => $gps['GPSLatitude'] ?? null,
            'gps_longitude' => $gps['GPSLongitude'] ?? null,
        ]);

        return [
            'camera' => $camera,
            'lens' => $lens,
            'taken_at' => $takenAt,
            'data' => $data !== [] ? $data : null,
        ];
    }

    /**
     * Keep only JSON-encodable scalar values, dropping nulls and binary blobs.
     *
     * @param  array<string,mixed>  $values
     * @return array<string,scalar>
     */
    private function scalarsOnly(array $values): array
    {
        return array_filter(
            array_map(
                fn ($value) => is_array($value) ? implode(',', array_map('strval', $value)) : $value,
                $values,
            ),
            fn ($value) => $value !== null && $value !== '' && (is_scalar($value)),
        );
    }

    /**
     * Generate any renditions $photo is missing, without re-ingesting it.
     *
     * Written for backfilling formats added after a photo was ingested. The
     * original is pulled to a temp file because it normally lives on a remote
     * disk that GD cannot read in place. Existing renditions are left alone
     * unless $force is set.
     *
     * @param  list<PhotoFormat>|null  $formats  defaults to every supported format
     * @return array{written:int, skipped:int, failed:int}
     */
    public function backfillVariants(Photo $photo, ?array $formats = null, bool $force = false): array
    {
        $formats = array_values(array_filter(
            $formats ?? $this->supportedFormats(),
            fn (PhotoFormat $format): bool => $this->gd->supportsFormat($format->value),
        ));

        $originalPath = (string) $photo->path;
        $summary = ['written' => 0, 'skipped' => 0, 'failed' => 0];

        if ($formats === [] || $originalPath === '') {
            return $summary;
        }

        $storage = Storage::disk($photo->disk ?? $this->diskName());

        if (! $storage->exists($originalPath)) {
            $summary['failed']++;

            return $summary;
        }

        $pending = [];

        foreach (PhotoVariant::cases() as $variant) {
            foreach ($formats as $format) {
                if (! $force && $storage->exists($variant->pathFor($originalPath, $format))) {
                    $summary['skipped']++;

                    continue;
                }

                $pending[] = [$variant, $format];
            }
        }

        if ($pending === []) {
            return $summary;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'gallery-backfill-');

        if ($tempPath === false) {
            $summary['failed'] += count($pending);

            return $summary;
        }

        try {
            $stream = $storage->readStream($originalPath);

            if ($stream === false || $stream === null) {
                $summary['failed'] += count($pending);

                return $summary;
            }

            $target = fopen($tempPath, 'wb');

            if ($target === false) {
                fclose($stream);
                $summary['failed'] += count($pending);

                return $summary;
            }

            try {
                stream_copy_to_stream($stream, $target);
            } finally {
                fclose($target);
                fclose($stream);
            }

            $imageInfo = @getimagesize($tempPath);
            $mime = is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';

            if (! in_array($mime, self::SUPPORTED_MIMES, true)) {
                $summary['failed'] += count($pending);

                return $summary;
            }

            $source = $this->gd->decode($tempPath, $mime);

            if (! $source instanceof \GdImage) {
                $summary['failed'] += count($pending);

                return $summary;
            }

            try {
                $source = $this->gd->applyExifOrientation($source, $tempPath, $mime);
                $sourceWidth = imagesx($source);
                $sourceHeight = imagesy($source);

                foreach ($pending as [$variant, $format]) {
                    try {
                        $written = $this->writeVariant($source, $sourceWidth, $sourceHeight, $originalPath, $variant, $format);
                    } catch (\Throwable) {
                        $written = false;
                    }

                    $written ? $summary['written']++ : $summary['failed']++;
                }
            } finally {
                imagedestroy($source);
            }
        } finally {
            @unlink($tempPath);
        }

        return $summary;
    }

    /**
     * Generate renditions next to the original, in every encoder-supported
     * format. Non-blocking: any failure is swallowed so the original stays the
     * source of truth.
     */
    private function generateVariants(string $sourcePath, string $mime, string $originalPath): void
    {
        $formats = $this->supportedFormats();

        if ($formats === []) {
            return;
        }

        $source = $this->gd->decode($sourcePath, $mime);

        if (! $source instanceof \GdImage) {
            return;
        }

        try {
            $source = $this->gd->applyExifOrientation($source, $sourcePath, $mime);
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);

            foreach (PhotoVariant::cases() as $variant) {
                foreach ($formats as $format) {
                    $this->writeVariant($source, $sourceWidth, $sourceHeight, $originalPath, $variant, $format);
                }
            }
        } catch (\Throwable) {
            // Swallow: variants are best-effort.
        } finally {
            imagedestroy($source);
        }
    }

    /**
     * Formats this PHP build can actually encode. A GD compiled without AVIF
     * support silently drops that format rather than failing the ingest.
     *
     * @return list<PhotoFormat>
     */
    private function supportedFormats(): array
    {
        return array_values(array_filter(
            PhotoFormat::cases(),
            fn (PhotoFormat $format): bool => $this->gd->supportsFormat($format->value),
        ));
    }

    /**
     * Encode and store one rendition. Returns whether the file actually landed
     * on the disk, so callers can report honest counts.
     */
    private function writeVariant(
        \GdImage $source,
        int $sourceWidth,
        int $sourceHeight,
        string $originalPath,
        PhotoVariant $variant,
        PhotoFormat $format,
    ): bool {
        $targetWidth = min($variant->maxWidth(), $sourceWidth);
        $targetHeight = max(1, (int) round($sourceHeight * ($targetWidth / $sourceWidth)));

        $canvas = $this->gd->resample($source, $format->mimeType(), $targetWidth, $targetHeight);

        if (! $canvas instanceof \GdImage) {
            return false;
        }

        try {
            $tempPath = tempnam(sys_get_temp_dir(), 'gallery-variant-');

            if ($tempPath === false || ! $this->gd->encode($canvas, $tempPath, $format->value, $format->quality())) {
                return false;
            }

            try {
                $stream = fopen($tempPath, 'rb');

                if ($stream === false) {
                    return false;
                }

                try {
                    return $this->storage()->writeStream($this->variantPath($originalPath, $variant, $format), $stream);
                } finally {
                    fclose($stream);
                }
            } finally {
                @unlink($tempPath);
            }
        } finally {
            imagedestroy($canvas);
        }
    }

    public function variantPath(string $originalPath, PhotoVariant $variant, PhotoFormat $format = PhotoFormat::Webp): string
    {
        return $variant->pathFor($originalPath, $format);
    }
}
