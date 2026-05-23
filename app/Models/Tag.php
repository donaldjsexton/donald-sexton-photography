<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use BelongsToSite;
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    public function journalPosts(): BelongsToMany
    {
        return $this->belongsToMany(JournalPost::class)->withTimestamps();
    }

    public function weddingStories(): BelongsToMany
    {
        return $this->belongsToMany(WeddingStory::class)->withTimestamps();
    }
}
