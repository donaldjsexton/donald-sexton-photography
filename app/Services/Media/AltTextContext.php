<?php

namespace App\Services\Media;

use App\Models\Media;

class AltTextContext
{
    /**
     * Short description of where an image is used, passed to the alt text
     * generator so it can name the event or venue.
     */
    public static function for(Media $media): ?string
    {
        $media->loadMissing(['weddingStories', 'journalPosts', 'venues']);

        $story = $media->weddingStories->first();

        if ($story) {
            $venueName = $story->venue?->name ?? $story->location_name ?? null;

            return $venueName
                ? $story->title.' — '.$venueName
                : $story->title;
        }

        $post = $media->journalPosts->first();

        if ($post) {
            return $post->title;
        }

        $venue = $media->venues->first();

        if ($venue) {
            return $venue->name.' wedding venue';
        }

        return null;
    }
}
