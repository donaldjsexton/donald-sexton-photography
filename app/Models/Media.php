<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'disk',
        'path',
        'filename',
        'mime_type',
        'width',
        'height',
        'alt_text',
        'caption',
        'credit',
        'focal_point_x',
        'focal_point_y',
        'original_wp_attachment_id',
    ];

    protected function casts(): array
    {
        return [
            'focal_point_x' => 'decimal:4',
            'focal_point_y' => 'decimal:4',
        ];
    }

    public function publicUrl(): ?string
    {
        if (! $this->path) {
            return null;
        }

        if (($this->disk ?? 'public') === 'public') {
            return '/storage/'.ltrim($this->path, '/');
        }

        return Storage::disk($this->disk ?? 'public')->url($this->path);
    }

    public function webpPath(): ?string
    {
        return $this->encodedSiblingPath('webp');
    }

    public function webpPublicUrl(): ?string
    {
        return $this->encodedSiblingPublicUrl('webp');
    }

    public function webpVariantPath(int $width): ?string
    {
        return $this->encodedVariantPath($width, 'webp');
    }

    public function webpVariantUrl(int $width): ?string
    {
        return $this->encodedVariantUrl($width, 'webp');
    }

    /**
     * Build a `srcset` string of WebP variants whose files exist on disk.
     *
     * @param  array<int>  $candidateWidths  widths to probe for variant files
     */
    public function webpSrcset(array $candidateWidths = [640, 1080, 1600]): ?string
    {
        return $this->encodedSrcset('webp', $candidateWidths);
    }

    public function avifPath(): ?string
    {
        return $this->encodedSiblingPath('avif');
    }

    public function avifPublicUrl(): ?string
    {
        return $this->encodedSiblingPublicUrl('avif');
    }

    public function avifVariantPath(int $width): ?string
    {
        return $this->encodedVariantPath($width, 'avif');
    }

    public function avifVariantUrl(int $width): ?string
    {
        return $this->encodedVariantUrl($width, 'avif');
    }

    /**
     * Build a `srcset` string of AVIF variants whose files exist on disk.
     *
     * @param  array<int>  $candidateWidths  widths to probe for variant files
     */
    public function avifSrcset(array $candidateWidths = [640, 1080, 1600]): ?string
    {
        return $this->encodedSrcset('avif', $candidateWidths);
    }

    /**
     * Sibling path next to the original at the same base name, e.g.
     * `photo.jpg` → `photo.{format}`. Only derived for raster originals.
     */
    private function encodedSiblingPath(string $format): ?string
    {
        $path = trim((string) $this->path);

        if ($path === '') {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            return null;
        }

        return preg_replace('/\.[^.]+$/', '.'.$format, $path);
    }

    private function encodedSiblingPublicUrl(string $format): ?string
    {
        $path = $this->encodedSiblingPath($format);

        if ($path === null) {
            return null;
        }

        $disk = $this->disk ?? 'public';

        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        if ($disk === 'public') {
            return '/storage/'.ltrim($path, '/');
        }

        return Storage::disk($disk)->url($path);
    }

    private function encodedVariantPath(int $width, string $format): ?string
    {
        $path = trim((string) $this->path);

        if ($path === '' || $width <= 0) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return null;
        }

        return preg_replace('/\.[^.]+$/', '-'.$width.'.'.$format, $path);
    }

    private function encodedVariantUrl(int $width, string $format): ?string
    {
        $path = $this->encodedVariantPath($width, $format);

        if ($path === null) {
            return null;
        }

        $disk = $this->disk ?? 'public';

        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        if ($disk === 'public') {
            return '/storage/'.ltrim($path, '/');
        }

        return Storage::disk($disk)->url($path);
    }

    /**
     * Build a `srcset` string of variant files of $format that exist on
     * disk. The full-size sibling at the original width is included as
     * the largest candidate when present. Returns null when none exist.
     *
     * @param  array<int>  $candidateWidths
     */
    private function encodedSrcset(string $format, array $candidateWidths): ?string
    {
        $entries = [];
        $sourceWidth = (int) ($this->width ?? 0);

        foreach ($candidateWidths as $width) {
            $width = (int) $width;

            if ($width <= 0) {
                continue;
            }

            if ($sourceWidth > 0 && $width >= $sourceWidth) {
                continue;
            }

            $url = $this->encodedVariantUrl($width, $format);

            if ($url === null) {
                continue;
            }

            $entries[$width] = $url.' '.$width.'w';
        }

        if ($entries === []) {
            return null;
        }

        $full = $this->encodedSiblingPublicUrl($format);

        if ($full !== null && $sourceWidth > 0) {
            $entries[$sourceWidth] = $full.' '.$sourceWidth.'w';
        }

        ksort($entries);

        return implode(', ', $entries);
    }

    public function objectPositionValue(): string
    {
        $x = $this->focal_point_x !== null ? max(0, min(1, (float) $this->focal_point_x)) : 0.5;
        $y = $this->focal_point_y !== null ? max(0, min(1, (float) $this->focal_point_y)) : 0.25;

        return round($x * 100, 2).'% '.round($y * 100, 2).'%';
    }

    public function pages(): MorphToMany
    {
        return $this->morphedByMany(Page::class, 'mediable')
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps();
    }

    public function weddingStories(): MorphToMany
    {
        return $this->morphedByMany(WeddingStory::class, 'mediable')
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps();
    }

    public function journalPosts(): MorphToMany
    {
        return $this->morphedByMany(JournalPost::class, 'mediable')
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps();
    }

    public function venues(): MorphToMany
    {
        return $this->morphedByMany(Venue::class, 'mediable')
            ->withPivot(['role', 'sort_order'])
            ->withTimestamps();
    }
}
