<?php

namespace App\Services\Media;

use App\Models\Media;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class MediaOptimizer
{
    public function __construct(private readonly GdImageProcessor $gd = new GdImageProcessor) {}

    /**
     * Standard upload pipeline: cap original dimensions, emit a full-size
     * WebP, and generate responsive WebP variants. Designed to be called
     * after a Media row has been saved with the original upload path.
     *
     * Failures are isolated per stage so a variant failure won't abort
     * the rest of the pipeline. Returns a combined result describing
     * what changed.
     *
     * @param  array<string,mixed>  $options  forwarded to optimize() and
     *                                        generateWebpVariants(); the
     *                                        optimizer-specific keys
     *                                        (max_width, *_quality, etc.)
     *                                        and `variant_widths` are
     *                                        recognized.
     * @return array{optimize:array<string,mixed>,variants:array<string,mixed>,avif_variants:array<string,mixed>}
     */
    public function optimizeUpload(Media $media, array $options = []): array
    {
        $variantWidths = $options['variant_widths'] ?? [640, 1080];
        unset($options['variant_widths']);

        $optimizeOptions = array_merge([
            'max_width' => 1600,
            'jpeg_quality' => 82,
            'webp_quality' => 80,
            'avif_quality' => 50,
            'min_bytes' => 0,
            'generate_webp' => true,
            'generate_avif' => true,
            'only_missing_webp' => true,
        ], $options);

        $optimizeResult = $this->optimize($media, $optimizeOptions);

        // Re-fetch dimensions after optimize() may have mutated the row.
        $media->refresh();

        $variantResult = $this->generateWebpVariants($media, (array) $variantWidths, [
            'webp_quality' => $optimizeOptions['webp_quality'],
        ]);

        $avifVariantResult = $this->generateAvifVariants($media, (array) $variantWidths, [
            'avif_quality' => $optimizeOptions['avif_quality'],
        ]);

        return [
            'optimize' => $optimizeResult,
            'variants' => $variantResult,
            'avif_variants' => $avifVariantResult,
        ];
    }

    public function optimize(Media $media, array $options = []): array
    {
        $disk = $media->disk ?: 'public';
        $path = trim((string) $media->path);

        if ($path === '') {
            return $this->skip('missing_path');
        }

        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            return $this->skip('missing_file');
        }

        $absolutePath = $storage->path($path);
        $imageInfo = @getimagesize($absolutePath);

        if (! is_array($imageInfo)) {
            return $this->skip('unreadable_image');
        }

        $mime = (string) ($imageInfo['mime'] ?? $media->mime_type ?? '');
        $supported = ['image/jpeg', 'image/png', 'image/webp'];

        if (! in_array($mime, $supported, true)) {
            return $this->skip('unsupported_mime');
        }

        $originalBytes = filesize($absolutePath);

        if ($originalBytes === false) {
            return $this->skip('missing_file_size');
        }

        $maxWidth = max(1, (int) ($options['max_width'] ?? 1600));
        $jpegQuality = max(1, min(100, (int) ($options['jpeg_quality'] ?? 82)));
        $webpQuality = max(1, min(100, (int) ($options['webp_quality'] ?? 80)));
        $avifQuality = max(1, min(100, (int) ($options['avif_quality'] ?? 50)));
        $minBytes = max(0, (int) ($options['min_bytes'] ?? 0));
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $generateWebp = (bool) ($options['generate_webp'] ?? false);
        $generateAvif = (bool) ($options['generate_avif'] ?? $generateWebp);
        $onlyMissingWebp = (bool) ($options['only_missing_webp'] ?? false);

        [$originalWidth, $originalHeight] = $imageInfo;
        $source = $this->gd->decode($absolutePath, $mime);

        if (! $source instanceof \GdImage) {
            return $this->skip('decoder_failed');
        }

        try {
            $source = $this->gd->applyExifOrientation($source, $absolutePath, $mime);
            $width = imagesx($source);
            $height = imagesy($source);
            $targetWidth = $width > $maxWidth ? $maxWidth : $width;
            $targetHeight = $width > $maxWidth
                ? max(1, (int) round($height * ($targetWidth / $width)))
                : $height;
            $resized = $targetWidth !== $width || $targetHeight !== $height;
            $webpPath = $this->siblingPathFor($path, 'webp');
            $webpExists = $webpPath !== null && $storage->exists($webpPath);
            $avifPath = $this->siblingPathFor($path, 'avif');
            $avifExists = $avifPath !== null && $storage->exists($avifPath);

            $webpSatisfied = ! $generateWebp || ($onlyMissingWebp && $webpExists);
            $avifSatisfied = ! $generateAvif
                || $avifPath === null
                || ! $this->gd->supportsFormat('avif')
                || ($onlyMissingWebp && $avifExists);

            if (
                ! $resized
                && $originalBytes < $minBytes
                && $webpSatisfied
                && $avifSatisfied
            ) {
                return $this->skip('below_min_bytes');
            }

            $canvas = $this->gd->resample($source, $mime, $targetWidth, $targetHeight);

            if (! $canvas instanceof \GdImage) {
                return $this->skip('canvas_failed');
            }

            try {
                $original = $this->optimizeOriginal(
                    media: $media,
                    mime: $mime,
                    absolutePath: $absolutePath,
                    canvas: $canvas,
                    originalBytes: (int) $originalBytes,
                    width: $targetWidth,
                    height: $targetHeight,
                    jpegQuality: $jpegQuality,
                    dryRun: $dryRun,
                );

                $webp = $this->optimizeEncodedSibling(
                    format: 'webp',
                    siblingPath: $webpPath,
                    storageDisk: $storage,
                    canvas: $canvas,
                    originalBytes: (int) $originalBytes,
                    generate: $generateWebp,
                    onlyMissing: $onlyMissingWebp,
                    existing: $webpExists,
                    quality: $webpQuality,
                    dryRun: $dryRun,
                );

                $avif = $this->optimizeEncodedSibling(
                    format: 'avif',
                    siblingPath: $avifPath,
                    storageDisk: $storage,
                    canvas: $canvas,
                    originalBytes: (int) $originalBytes,
                    generate: $generateAvif,
                    onlyMissing: $onlyMissingWebp,
                    existing: $avifExists,
                    quality: $avifQuality,
                    dryRun: $dryRun,
                );

                $status = $original['optimized']
                    || $webp['created'] || $webp['updated']
                    || $avif['created'] || $avif['updated']
                    ? 'optimized'
                    : 'skipped';

                return [
                    'status' => $status,
                    'reason' => $status === 'optimized' ? null : 'no_gain',
                    'original_bytes' => (int) $originalBytes,
                    'optimized_bytes' => (int) ($original['optimized_bytes'] ?? $originalBytes),
                    'bytes_saved' => max(0, (int) ($original['bytes_saved'] ?? 0)),
                    'resized' => $resized && $original['optimized'],
                    'webp_created' => (bool) $webp['created'],
                    'webp_updated' => (bool) $webp['updated'],
                    'webp_bytes' => $webp['bytes'],
                    'avif_created' => (bool) $avif['created'],
                    'avif_updated' => (bool) $avif['updated'],
                    'avif_bytes' => $avif['bytes'],
                ];
            } finally {
                imagedestroy($canvas);
            }
        } finally {
            imagedestroy($source);
        }
    }

    private function optimizeOriginal(
        Media $media,
        string $mime,
        string $absolutePath,
        \GdImage $canvas,
        int $originalBytes,
        int $width,
        int $height,
        int $jpegQuality,
        bool $dryRun,
    ): array {
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return [
                'optimized' => false,
                'optimized_bytes' => $originalBytes,
                'bytes_saved' => 0,
            ];
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'media-opt-');

        if ($tempPath === false) {
            return [
                'optimized' => false,
                'optimized_bytes' => $originalBytes,
                'bytes_saved' => 0,
            ];
        }

        try {
            $written = match ($mime) {
                'image/jpeg' => $this->writeJpeg($canvas, $tempPath, $jpegQuality),
                'image/png' => imagepng($canvas, $tempPath, 9),
                'image/webp' => imagewebp($canvas, $tempPath, 80),
            };

            if (! $written) {
                return [
                    'optimized' => false,
                    'optimized_bytes' => $originalBytes,
                    'bytes_saved' => 0,
                ];
            }

            $optimizedBytes = filesize($tempPath);

            if ($optimizedBytes === false) {
                return [
                    'optimized' => false,
                    'optimized_bytes' => $originalBytes,
                    'bytes_saved' => 0,
                ];
            }

            $shouldReplace = $width !== (int) ($media->width ?: $width)
                || $height !== (int) ($media->height ?: $height)
                || $optimizedBytes < $originalBytes;

            if (! $shouldReplace) {
                return [
                    'optimized' => false,
                    'optimized_bytes' => $originalBytes,
                    'bytes_saved' => 0,
                ];
            }

            if (! $dryRun) {
                if (! @rename($tempPath, $absolutePath)) {
                    copy($tempPath, $absolutePath);
                    @unlink($tempPath);
                }

                $media->width = $width;
                $media->height = $height;
                $media->mime_type = $mime;
                $media->save();
            }

            return [
                'optimized' => true,
                'optimized_bytes' => (int) $optimizedBytes,
                'bytes_saved' => max(0, $originalBytes - (int) $optimizedBytes),
            ];
        } finally {
            @unlink($tempPath);
        }
    }

    private function optimizeEncodedSibling(
        string $format,
        ?string $siblingPath,
        Filesystem $storageDisk,
        \GdImage $canvas,
        int $originalBytes,
        bool $generate,
        bool $onlyMissing,
        bool $existing,
        int $quality,
        bool $dryRun,
    ): array {
        if (! $generate || $siblingPath === null || ! $this->gd->supportsFormat($format)) {
            return ['created' => false, 'updated' => false, 'bytes' => null];
        }

        if ($onlyMissing && $existing) {
            return ['created' => false, 'updated' => false, 'bytes' => null];
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'media-'.$format.'-');

        if ($tempPath === false) {
            return ['created' => false, 'updated' => false, 'bytes' => null];
        }

        try {
            if (! $this->gd->encode($canvas, $tempPath, $format, $quality)) {
                return ['created' => false, 'updated' => false, 'bytes' => null];
            }

            $encodedBytes = filesize($tempPath);

            if ($encodedBytes === false) {
                return ['created' => false, 'updated' => false, 'bytes' => null];
            }

            if ($existing) {
                $existingBytes = $storageDisk->size($siblingPath);

                if ($existingBytes !== false && $existingBytes <= $encodedBytes) {
                    return ['created' => false, 'updated' => false, 'bytes' => (int) $existingBytes];
                }
            } elseif ($encodedBytes >= $originalBytes) {
                return ['created' => false, 'updated' => false, 'bytes' => (int) $encodedBytes];
            }

            if (! $dryRun) {
                $stream = fopen($tempPath, 'rb');

                if ($stream === false) {
                    return ['created' => false, 'updated' => false, 'bytes' => null];
                }

                try {
                    $storageDisk->writeStream($siblingPath, $stream);
                } finally {
                    fclose($stream);
                }
            }

            return [
                'created' => ! $existing,
                'updated' => $existing,
                'bytes' => (int) $encodedBytes,
            ];
        } finally {
            @unlink($tempPath);
        }
    }

    private function writeJpeg(\GdImage $canvas, string $path, int $quality): bool
    {
        imageinterlace($canvas, true);

        return imagejpeg($canvas, $path, $quality);
    }

    private function siblingPathFor(string $path, string $format): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            return null;
        }

        return preg_replace('/\.[^.]+$/', '.'.$format, $path);
    }

    /**
     * Generate downscaled WebP variants of $media at each width in $widths.
     *
     * @param  array<int>  $widths
     * @param  array<string,mixed>  $options  webp_quality, force, dry_run
     * @return array{status:string,reason:?string,variants:array<int,array<string,mixed>>}
     */
    public function generateWebpVariants(Media $media, array $widths, array $options = []): array
    {
        return $this->generateVariants('webp', $media, $widths, [
            'quality' => (int) ($options['webp_quality'] ?? 80),
            'force' => (bool) ($options['force'] ?? false),
            'dry_run' => (bool) ($options['dry_run'] ?? false),
        ]);
    }

    /**
     * Generate downscaled AVIF variants of $media at each width in $widths.
     *
     * @param  array<int>  $widths
     * @param  array<string,mixed>  $options  avif_quality, force, dry_run
     * @return array{status:string,reason:?string,variants:array<int,array<string,mixed>>}
     */
    public function generateAvifVariants(Media $media, array $widths, array $options = []): array
    {
        return $this->generateVariants('avif', $media, $widths, [
            'quality' => (int) ($options['avif_quality'] ?? 50),
            'force' => (bool) ($options['force'] ?? false),
            'dry_run' => (bool) ($options['dry_run'] ?? false),
        ]);
    }

    /**
     * Generate downscaled variants of $media in $format at each width.
     *
     * Output paths follow the pattern path/foo-{width}.{format} next to
     * the original. Widths greater than or equal to the source width are
     * skipped to avoid upscaling.
     *
     * @param  array<int>  $widths
     * @param  array<string,mixed>  $options  quality, force, dry_run
     * @return array{status:string,reason:?string,variants:array<int,array<string,mixed>>}
     */
    private function generateVariants(string $format, Media $media, array $widths, array $options = []): array
    {
        $disk = $media->disk ?: 'public';
        $path = trim((string) $media->path);

        if ($path === '') {
            return $this->skipVariants('missing_path');
        }

        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            return $this->skipVariants('missing_file');
        }

        $absolutePath = $storage->path($path);
        $imageInfo = @getimagesize($absolutePath);

        if (! is_array($imageInfo)) {
            return $this->skipVariants('unreadable_image');
        }

        $mime = (string) ($imageInfo['mime'] ?? $media->mime_type ?? '');

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return $this->skipVariants('unsupported_mime');
        }

        if (! $this->gd->supportsFormat($format)) {
            return $this->skipVariants($format.'_unsupported');
        }

        $widths = collect($widths)
            ->map(fn ($width) => (int) $width)
            ->filter(fn (int $width) => $width > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($widths === []) {
            return $this->skipVariants('no_widths');
        }

        $quality = max(1, min(100, (int) ($options['quality'] ?? ($format === 'avif' ? 50 : 80))));
        $force = (bool) ($options['force'] ?? false);
        $dryRun = (bool) ($options['dry_run'] ?? false);

        $source = $this->gd->decode($absolutePath, $mime);

        if (! $source instanceof \GdImage) {
            return $this->skipVariants('decoder_failed');
        }

        $results = [];

        try {
            $source = $this->gd->applyExifOrientation($source, $absolutePath, $mime);
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);

            foreach ($widths as $width) {
                if ($width >= $sourceWidth) {
                    $results[] = [
                        'width' => $width,
                        'created' => false,
                        'updated' => false,
                        'bytes' => null,
                        'reason' => 'source_smaller_than_target',
                    ];

                    continue;
                }

                $variantPath = $format === 'avif'
                    ? $media->avifVariantPath($width)
                    : $media->webpVariantPath($width);

                if ($variantPath === null) {
                    $results[] = [
                        'width' => $width,
                        'created' => false,
                        'updated' => false,
                        'bytes' => null,
                        'reason' => 'unsupported_extension',
                    ];

                    continue;
                }

                $exists = $storage->exists($variantPath);

                if ($exists && ! $force) {
                    $results[] = [
                        'width' => $width,
                        'created' => false,
                        'updated' => false,
                        'bytes' => (int) $storage->size($variantPath),
                        'reason' => 'already_exists',
                    ];

                    continue;
                }

                $targetHeight = max(1, (int) round($sourceHeight * ($width / $sourceWidth)));
                $canvas = $this->gd->resample($source, 'image/webp', $width, $targetHeight);

                if (! $canvas instanceof \GdImage) {
                    $results[] = [
                        'width' => $width,
                        'created' => false,
                        'updated' => false,
                        'bytes' => null,
                        'reason' => 'canvas_failed',
                    ];

                    continue;
                }

                $tempPath = tempnam(sys_get_temp_dir(), 'media-variant-');

                try {
                    if ($tempPath === false || ! $this->gd->encode($canvas, $tempPath, $format, $quality)) {
                        $results[] = [
                            'width' => $width,
                            'created' => false,
                            'updated' => false,
                            'bytes' => null,
                            'reason' => 'encode_failed',
                        ];

                        continue;
                    }

                    $variantBytes = filesize($tempPath);

                    if ($variantBytes === false) {
                        $results[] = [
                            'width' => $width,
                            'created' => false,
                            'updated' => false,
                            'bytes' => null,
                            'reason' => 'missing_variant_size',
                        ];

                        continue;
                    }

                    if (! $dryRun) {
                        $stream = fopen($tempPath, 'rb');

                        if ($stream === false) {
                            $results[] = [
                                'width' => $width,
                                'created' => false,
                                'updated' => false,
                                'bytes' => null,
                                'reason' => 'stream_failed',
                            ];

                            continue;
                        }

                        try {
                            $storage->writeStream($variantPath, $stream);
                        } finally {
                            fclose($stream);
                        }
                    }

                    $results[] = [
                        'width' => $width,
                        'created' => ! $exists,
                        'updated' => $exists,
                        'bytes' => (int) $variantBytes,
                        'reason' => null,
                    ];
                } finally {
                    if ($tempPath !== false) {
                        @unlink($tempPath);
                    }

                    imagedestroy($canvas);
                }
            }
        } finally {
            imagedestroy($source);
        }

        $changed = collect($results)->contains(fn (array $row) => $row['created'] || $row['updated']);

        return [
            'status' => $changed ? 'optimized' : 'skipped',
            'reason' => $changed ? null : 'no_change',
            'variants' => $results,
        ];
    }

    /**
     * @return array{status:string,reason:?string,variants:array<int,array<string,mixed>>}
     */
    private function skipVariants(string $reason): array
    {
        return [
            'status' => 'skipped',
            'reason' => $reason,
            'variants' => [],
        ];
    }

    private function skip(string $reason): array
    {
        return [
            'status' => 'skipped',
            'reason' => $reason,
            'original_bytes' => 0,
            'optimized_bytes' => 0,
            'bytes_saved' => 0,
            'resized' => false,
            'webp_created' => false,
            'webp_updated' => false,
            'webp_bytes' => null,
        ];
    }
}
