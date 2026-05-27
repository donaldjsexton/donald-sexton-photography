<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Renders 1200x630 PNG social cards using GD. Source image is centered and
 * dark-blended so the title and brand mark stay legible regardless of the
 * underlying photograph. Output is cached on the public disk under
 * `og/{type}/{key}.png` and served byte-for-byte on subsequent requests.
 */
class OpenGraphImageRenderer
{
    private const WIDTH = 1200;

    private const HEIGHT = 630;

    private const DISK = 'public';

    private const BG_R = 22;

    private const BG_G = 15;

    private const BG_B = 10;

    private const FG_R = 249;

    private const FG_G = 243;

    private const FG_B = 236;

    /**
     * Resolve the disk path for the cached image, generating it when missing.
     */
    public function render(
        string $type,
        string $cacheKey,
        string $title,
        ?string $eyebrow,
        ?string $sourceImagePath,
    ): string {
        $relativePath = 'og/'.$type.'/'.$cacheKey.'.png';
        $disk = Storage::disk(self::DISK);

        if ($disk->exists($relativePath)) {
            return $relativePath;
        }

        $canvas = $this->compose($title, $eyebrow, $sourceImagePath);

        ob_start();
        imagepng($canvas, null, 6);
        $bytes = (string) ob_get_clean();
        imagedestroy($canvas);

        $disk->put($relativePath, $bytes, ['visibility' => 'public']);

        return $relativePath;
    }

    /**
     * @return \GdImage
     */
    private function compose(string $title, ?string $eyebrow, ?string $sourceImagePath)
    {
        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        if ($canvas === false) {
            throw new \RuntimeException('Unable to allocate OG canvas');
        }

        $background = imagecolorallocate($canvas, self::BG_R, self::BG_G, self::BG_B);
        imagefilledrectangle($canvas, 0, 0, self::WIDTH, self::HEIGHT, (int) $background);

        if ($sourceImagePath !== null) {
            $this->placeSourceImage($canvas, $sourceImagePath);
            $this->applyDarkenGradient($canvas);
        }

        $this->drawText($canvas, $title, $eyebrow);

        return $canvas;
    }

    private function placeSourceImage(\GdImage $canvas, string $sourcePath): void
    {
        $source = $this->loadImage($sourcePath);

        if ($source === null) {
            return;
        }

        $sw = imagesx($source);
        $sh = imagesy($source);

        if ($sw <= 0 || $sh <= 0) {
            imagedestroy($source);

            return;
        }

        $targetRatio = self::WIDTH / self::HEIGHT;
        $sourceRatio = $sw / $sh;

        if ($sourceRatio > $targetRatio) {
            $cropH = $sh;
            $cropW = (int) round($sh * $targetRatio);
            $cropX = (int) round(($sw - $cropW) / 2);
            $cropY = 0;
        } else {
            $cropW = $sw;
            $cropH = (int) round($sw / $targetRatio);
            $cropX = 0;
            $cropY = (int) round(($sh - $cropH) / 2);
        }

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            $cropX,
            $cropY,
            self::WIDTH,
            self::HEIGHT,
            $cropW,
            $cropH,
        );

        imagedestroy($source);
    }

    /**
     * Bottom-up dark gradient so title text is always legible regardless of
     * the photograph behind it.
     */
    private function applyDarkenGradient(\GdImage $canvas): void
    {
        $startY = (int) (self::HEIGHT * 0.35);

        for ($y = $startY; $y < self::HEIGHT; $y++) {
            $progress = ($y - $startY) / (self::HEIGHT - $startY);
            $alpha = (int) round(110 - ($progress * 60));
            $alpha = max(50, min(110, $alpha));

            $color = imagecolorallocatealpha($canvas, 0, 0, 0, $alpha);

            if ($color === false) {
                continue;
            }

            imagefilledrectangle($canvas, 0, $y, self::WIDTH, $y, (int) $color);
        }
    }

    private function drawText(\GdImage $canvas, string $title, ?string $eyebrow): void
    {
        $serif = resource_path('fonts/DejaVuSerif-Bold.ttf');
        $sans = resource_path('fonts/DejaVuSans.ttf');

        if (! is_file($serif) || ! is_file($sans)) {
            return;
        }

        $foreground = imagecolorallocate($canvas, self::FG_R, self::FG_G, self::FG_B);
        $muted = imagecolorallocate($canvas, 215, 205, 192);

        $padding = 72;

        if ($eyebrow !== null && $eyebrow !== '') {
            imagettftext(
                $canvas,
                22,
                0,
                $padding,
                self::HEIGHT - 280,
                (int) $muted,
                $sans,
                mb_strtoupper($eyebrow, 'UTF-8'),
            );
        }

        $titleLines = $this->wrapLines(
            text: $title,
            fontPath: $serif,
            fontSize: 60,
            maxWidth: self::WIDTH - ($padding * 2),
            maxLines: 3,
        );

        $lineHeight = 78;
        $titleY = self::HEIGHT - 220;

        foreach ($titleLines as $i => $line) {
            imagettftext(
                $canvas,
                60,
                0,
                $padding,
                $titleY + ($i * $lineHeight),
                (int) $foreground,
                $serif,
                $line,
            );
        }

        imagettftext(
            $canvas,
            22,
            0,
            $padding,
            self::HEIGHT - 60,
            (int) $foreground,
            $sans,
            'Donald Sexton Photography',
        );

        imagettftext(
            $canvas,
            18,
            0,
            self::WIDTH - 360,
            self::HEIGHT - 60,
            (int) $muted,
            $sans,
            'donaldsextonphotography.com',
        );
    }

    /**
     * @return array<int, string>
     */
    private function wrapLines(string $text, string $fontPath, int $fontSize, int $maxWidth, int $maxLines): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            $box = imagettfbbox($fontSize, 0, $fontPath, $candidate);

            if ($box === false) {
                continue;
            }

            $width = abs($box[2] - $box[0]);

            if ($width > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;

                if (count($lines) >= $maxLines) {
                    break;
                }
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '' && count($lines) < $maxLines) {
            $lines[] = $current;
        }

        if (count($lines) >= $maxLines) {
            $lastIndex = $maxLines - 1;
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$lastIndex] = $this->ellipsize($lines[$lastIndex], $fontPath, $fontSize, $maxWidth);
        }

        return $lines;
    }

    private function ellipsize(string $line, string $fontPath, int $fontSize, int $maxWidth): string
    {
        $ellipsis = '…';
        $candidate = $line;

        while ($candidate !== '') {
            $box = imagettfbbox($fontSize, 0, $fontPath, $candidate.$ellipsis);

            if ($box !== false && abs($box[2] - $box[0]) <= $maxWidth) {
                return $candidate.$ellipsis;
            }

            $candidate = mb_substr($candidate, 0, -1);
        }

        return $line;
    }

    /**
     * @return \GdImage|null
     */
    private function loadImage(string $path)
    {
        if (! is_readable($path)) {
            return null;
        }

        $info = @getimagesize($path);

        if ($info === false) {
            return null;
        }

        return match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($path) ?: null,
            'image/png' => @imagecreatefrompng($path) ?: null,
            'image/webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            default => null,
        };
    }
}
