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

        return \Illuminate\Support\Facades\Storage::disk($this->disk ?? 'public')->url($this->path);
    }

    public function webpPath(): ?string
    {
        $path = trim((string) $this->path);

        if ($path === '') {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            return null;
        }

        return preg_replace('/\.[^.]+$/', '.webp', $path);
    }

    public function webpPublicUrl(): ?string
    {
        $path = $this->webpPath();

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
