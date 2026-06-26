<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Database\Factories\GalleryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Gallery extends Model
{
    use BelongsToSite;

    /** @use HasFactory<GalleryFactory> */
    use HasFactory;

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_PUBLIC = 'public';

    protected $fillable = [
        'site_id',
        'uuid',
        'slug',
        'title',
        'description',
        'visibility',
        'password',
        'cover_photo_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Gallery $gallery): void {
            if (empty($gallery->uuid)) {
                $gallery->uuid = (string) Str::uuid();
            }

            if (empty($gallery->slug) && ! empty($gallery->title)) {
                $gallery->slug = Str::slug($gallery->title);
            }
        });
    }

    /**
     * @return HasMany<Album, $this>
     */
    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    /**
     * @return BelongsTo<Photo, $this>
     */
    public function coverPhoto(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'cover_photo_id');
    }

    /**
     * @return MorphMany<ShareToken, $this>
     */
    public function shareTokens(): MorphMany
    {
        return $this->morphMany(ShareToken::class, 'shareable');
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }
}
