<?php

namespace App\Services\Media;

/**
 * Stateless GD primitives shared by the media optimizer and the gallery
 * ingestion pipeline: decoding, EXIF-orientation correction, alpha-aware
 * resampling, and format-aware encoding. Centralizing these keeps a single
 * correct implementation (notably of the eight EXIF orientations) instead of
 * parallel copies drifting apart.
 */
class GdImageProcessor
{
    public function decode(string $absolutePath, string $mime): ?\GdImage
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

    public function applyExifOrientation(\GdImage $image, string $absolutePath, string $mime): \GdImage
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

    /**
     * Resample a source image into a new canvas of the target dimensions,
     * preserving transparency for PNG/WebP.
     */
    public function resample(\GdImage $source, string $mime, int $targetWidth, int $targetHeight): ?\GdImage
    {
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $canvas instanceof \GdImage) {
            return null;
        }

        $this->prepareCanvas($canvas, $mime);

        if (imagesx($source) === $targetWidth && imagesy($source) === $targetHeight) {
            imagecopy($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight);

            return $canvas;
        }

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

    public function supportsFormat(string $format): bool
    {
        return match ($format) {
            'webp' => function_exists('imagewebp'),
            'avif' => function_exists('imageavif'),
            default => false,
        };
    }

    public function encode(\GdImage $canvas, string $path, string $format, int $quality): bool
    {
        return match ($format) {
            'webp' => function_exists('imagewebp') && imagewebp($canvas, $path, $quality),
            'avif' => function_exists('imageavif') && imageavif($canvas, $path, $quality),
            default => false,
        };
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

    private function flip(\GdImage $image, int $mode): \GdImage
    {
        if (function_exists('imageflip')) {
            imageflip($image, $mode);
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
}
