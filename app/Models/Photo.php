<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use App\Services\Galleries\PhotoVariant;
use Database\Factories\PhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Photo extends Model
{
    use BelongsToSite;

    /** @use HasFactory<PhotoFactory> */
    use HasFactory;

    protected $fillable = [
        'site_id',
        'uuid',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'sha256',
        'width',
        'height',
        'camera',
        'lens',
        'taken_at',
        'exif',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'taken_at' => 'datetime',
            'exif' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Photo $photo): void {
            if (empty($photo->uuid)) {
                $photo->uuid = (string) Str::uuid();
            }
        });

        static::deleting(function (Photo $photo): void {
            $photo->deleteFiles();
        });
    }

    /**
     * Remove the original and every generated rendition from storage.
     */
    public function deleteFiles(): void
    {
        if (! $this->path) {
            return;
        }

        $disk = Storage::disk($this->disk ?? 's3');
        $disk->delete($this->path);

        foreach (PhotoVariant::cases() as $variant) {
            $disk->delete($this->variantPath($variant));
        }
    }

    /**
     * @return BelongsToMany<Album, $this>
     */
    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class)
            ->withPivotValue('site_id', $this->site_id)
            ->withPivot(['sort_order', 'added_at'])
            ->withTimestamps();
    }

    public function url(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return Storage::disk($this->disk ?? 's3')->url($this->path);
    }

    public function variantPath(PhotoVariant $variant): string
    {
        return $variant->pathFor((string) $this->path);
    }

    /**
     * Resolve the best available path for a rendition, falling back to the
     * original when the variant was not generated.
     */
    public function pathForVariant(PhotoVariant $variant): string
    {
        $variantPath = $this->variantPath($variant);

        if (Storage::disk($this->disk ?? 's3')->exists($variantPath)) {
            return $variantPath;
        }

        return (string) $this->path;
    }

    public function downloadName(): string
    {
        return $this->original_name ?: $this->uuid.'.'.pathinfo((string) $this->path, PATHINFO_EXTENSION);
    }
}
