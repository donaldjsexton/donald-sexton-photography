<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Database\Factories\AlbumFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Album extends Model
{
    use BelongsToSite;

    /** @use HasFactory<AlbumFactory> */
    use HasFactory;

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_PUBLIC = 'public';

    protected $fillable = [
        'site_id',
        'gallery_id',
        'name',
        'description',
        'visibility',
        'cover_photo_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Gallery, $this>
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    /**
     * @return BelongsToMany<Photo, $this>
     */
    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class)
            ->withPivot(['site_id', 'sort_order', 'added_at'])
            ->withTimestamps()
            ->orderBy('album_photo.sort_order');
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
}
