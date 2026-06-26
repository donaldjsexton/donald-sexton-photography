<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Database\Factories\GalleryFactory;
use Illuminate\Database\Eloquent\Collection;
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
        'client_id',
        'booked_job_id',
        'uuid',
        'slug',
        'title',
        'description',
        'visibility',
        'requires_payment',
        'password',
        'cover_photo_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'requires_payment' => 'boolean',
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
                $gallery->slug = $gallery->uniqueSlug(Str::slug($gallery->title));
            }
        });
    }

    /**
     * Build a slug unique within the gallery's site, suffixing -2, -3, … on
     * collision so the composite unique(site_id, slug) index is never violated.
     */
    private function uniqueSlug(string $base): string
    {
        $base = $base !== '' ? $base : 'gallery';
        $slug = $base;
        $suffix = 2;

        while (
            static::query()
                ->withoutGlobalScope('site')
                ->where('site_id', $this->site_id)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
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
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<BookedJob, $this>
     */
    public function bookedJob(): BelongsTo
    {
        return $this->belongsTo(BookedJob::class);
    }

    /**
     * Whether full-resolution downloads are withheld. Only gated when the
     * gallery opts in and its owning client has a genuinely owed, unpaid
     * balance; proofing/viewing is never gated. A gallery with no owning client
     * has no balance to enforce, so it is not gated.
     */
    public function downloadsLocked(): bool
    {
        if (! $this->requires_payment || $this->client_id === null) {
            return false;
        }

        // A single existence check on owed-but-unpaid invoices: excludes draft
        // (not yet owed), paid, void, and refunded, and avoids hydrating rows.
        return (bool) $this->client?->invoices()
            ->whereIn('status', [
                Invoice::STATUS_SENT,
                Invoice::STATUS_PARTIALLY_PAID,
                Invoice::STATUS_OVERDUE,
            ])
            ->whereColumn('amount_paid_cents', '<', 'total_cents')
            ->exists();
    }

    /**
     * Resolve a single photo in this gallery by its public uuid, verifying
     * album membership in one indexed query rather than loading every photo.
     */
    public function findPhotoByUuid(string $uuid): ?Photo
    {
        return Photo::query()
            ->where('photos.uuid', $uuid)
            ->whereExists(function ($query): void {
                $query->from('album_photo')
                    ->join('albums', 'albums.id', '=', 'album_photo.album_id')
                    ->whereColumn('album_photo.photo_id', 'photos.id')
                    ->where('albums.gallery_id', $this->id);
            })
            ->first();
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

    /**
     * Every photo across the gallery's albums, ordered by album then position.
     *
     * @return Collection<int, Photo>
     */
    public function orderedPhotos(): Collection
    {
        // Deduped in PHP rather than via SELECT DISTINCT: a DISTINCT with an
        // ORDER BY on the joined sort columns is invalid on PostgreSQL, and
        // unique() keeps the first (lowest-ordered) occurrence deterministically.
        return Photo::query()
            ->join('album_photo', 'album_photo.photo_id', '=', 'photos.id')
            ->join('albums', 'albums.id', '=', 'album_photo.album_id')
            ->where('albums.gallery_id', $this->id)
            ->orderBy('albums.sort_order')
            ->orderBy('album_photo.sort_order')
            ->select('photos.*')
            ->get()
            ->unique('id')
            ->values();
    }
}
