<?php

namespace App\Services\Media;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

class MediaOptimizer
{
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
        $minBytes = max(0, (int) ($options['min_bytes'] ?? 0));
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $generateWebp = (bool) ($options['generate_webp'] ?? false);
        $onlyMissingWebp = (bool) ($options['only_missing_webp'] ?? false);

        [$originalWidth, $originalHeight] = $imageInfo;
        $source = $this->createImageResource($absolutePath, $mime);

        if (! $source instanceof \GdImage) {
            return $this->skip('decoder_failed');
        }

        try {
            $source = $this->applyExifOrientation($source, $absolutePath, $mime);
            $width = imagesx($source);
            $height = imagesy($source);
            $targetWidth = $width > $maxWidth ? $maxWidth : $width;
            $targetHeight = $width > $maxWidth
                ? max(1, (int) round($height * ($targetWidth / $width)))
                : $height;
            $resized = $targetWidth !== $width || $targetHeight !== $height;
            $webpPath = $this->webpPathFor($path);
            $webpExists = $webpPath !== null && $storage->exists($webpPath);

            if (
                ! $resized
                && $originalBytes < $minBytes
                && (! $generateWebp || ($onlyMissingWebp && $webpExists))
            ) {
                return $this->skip('below_min_bytes');
            }

            $canvas = $this->makeCanvas($source, $mime, $targetWidth, $targetHeight);

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

                $webp = $this->optimizeWebpVariant(
                    storagePath: $path,
                    storageDisk: $storage,
                    canvas: $canvas,
                    originalBytes: (int) $originalBytes,
                    generateWebp: $generateWebp,
                    onlyMissingWebp: $onlyMissingWebp,
                    existingWebp: $webpExists,
                    webpQuality: $webpQuality,
                    dryRun: $dryRun,
                );

                $status = $original['optimized'] || $webp['created'] || $webp['updated']
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

    private function optimizeWebpVariant(
        string $storagePath,
        \Illuminate\Contracts\Filesystem\Filesystem $storageDisk,
        \GdImage $canvas,
        int $originalBytes,
        bool $generateWebp,
        bool $onlyMissingWebp,
        bool $existingWebp,
        int $webpQuality,
        bool $dryRun,
    ): array {
        $webpPath = $this->webpPathFor($storagePath);

        if (! $generateWebp || $webpPath === null || ! function_exists('imagewebp')) {
            return ['created' => false, 'updated' => false, 'bytes' => null];
        }

        if ($onlyMissingWebp && $existingWebp) {
            return ['created' => false, 'updated' => false, 'bytes' => null];
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'media-webp-');

        if ($tempPath === false) {
            return ['created' => false, 'updated' => false, 'bytes' => null];
        }

        try {
            if (! imagewebp($canvas, $tempPath, $webpQuality)) {
                return ['created' => false, 'updated' => false, 'bytes' => null];
            }

            $webpBytes = filesize($tempPath);

            if ($webpBytes === false) {
                return ['created' => false, 'updated' => false, 'bytes' => null];
            }

            if ($existingWebp) {
                $existingBytes = $storageDisk->size($webpPath);

                if ($existingBytes !== false && $existingBytes <= $webpBytes) {
                    return ['created' => false, 'updated' => false, 'bytes' => (int) $existingBytes];
                }
            } elseif ($webpBytes >= $originalBytes) {
                return ['created' => false, 'updated' => false, 'bytes' => (int) $webpBytes];
            }

            if (! $dryRun) {
                $stream = fopen($tempPath, 'rb');

                if ($stream === false) {
                    return ['created' => false, 'updated' => false, 'bytes' => null];
                }

                try {
                    $storageDisk->writeStream($webpPath, $stream);
                } finally {
                    fclose($stream);
                }
            }

            return [
                'created' => ! $existingWebp,
                'updated' => $existingWebp,
                'bytes' => (int) $webpBytes,
            ];
        } finally {
            @unlink($tempPath);
        }
    }

    private function createImageResource(string $absolutePath, string $mime): ?\GdImage
    {
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($absolutePath) ?: null,
            'image/png' => @imagecreatefrompng($absolutePath) ?: null,
            'image/webp' => function_exists('imagecreatefromwebp')
                ? (@imagecreatefromwebp($absolutePath) ?: null)
                : null,
            default => null,
        };
    }

    private function applyExifOrientation(\GdImage $image, string $absolutePath, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        try {
            $exif = @exif_read_data($absolutePath);
            $orientation = (int) ($exif['Orientation'] ?? 1);
        } catch (\Throwable) {
            return $image;
        }

        return match ($orientation) {
            2 => $this->flip($image, IMG_FLIP_HORIZONTAL),
            3 => $this->rotate($image, 180),
            4 => $this->flip($image, IMG_FLIP_VERTICAL),
            5 => $this->flip($this->rotate($image, -90), IMG_FLIP_HORIZONTAL),
            6 => $this->rotate($image, -90),
            7 => $this->flip($this->rotate($image, 90), IMG_FLIP_HORIZONTAL),
            8 => $this->rotate($image, 90),
            default => $image,
        };
    }

    private function flip(\GdImage $image, int $mode): \GdImage
    {
        if (function_exists('imageflip') && imageflip($image, $mode)) {
            return $image;
        }

        return $image;
    }

    private function rotate(\GdImage $image, int $degrees): \GdImage
    {
        $rotated = imagerotate($image, $degrees, 0);

        if ($rotated instanceof \GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    private function makeCanvas(\GdImage $source, string $mime, int $targetWidth, int $targetHeight): ?\GdImage
    {
        if (imagesx($source) === $targetWidth && imagesy($source) === $targetHeight) {
            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

            if (! $canvas instanceof \GdImage) {
                return null;
            }

            $this->prepareCanvas($canvas, $mime);
            imagecopy($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight);

            return $canvas;
        }

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $canvas instanceof \GdImage) {
            return null;
        }

        $this->prepareCanvas($canvas, $mime);

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            imagesx($source),
            imagesy($source)
        );

        return $canvas;
    }

    private function prepareCanvas(\GdImage $canvas, string $mime): void
    {
        if (in_array($mime, ['image/png', 'image/webp'], true)) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, imagesx($canvas), imagesy($canvas), $transparent);
        } else {
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, imagesx($canvas), imagesy($canvas), $white);
        }
    }

    private function writeJpeg(\GdImage $canvas, string $path, int $quality): bool
    {
        imageinterlace($canvas, true);

        return imagejpeg($canvas, $path, $quality);
    }

    private function webpPathFor(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            return null;
        }

        return preg_replace('/\.[^.]+$/', '.webp', $path);
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
