<?php

namespace App\Mcp;

use App\Models\Media;
use App\Models\WeddingStory;

class StoryPayload
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(WeddingStory $story): array
    {
        $story->load(['heroMedia', 'media']);

        return [
            'id' => $story->id,
            'title' => $story->title,
            'slug' => $story->slug,
            'status' => $story->status,
            'story_type' => $story->story_type,
            'published_at' => $story->published_at?->toIso8601String(),
            'excerpt' => $story->excerpt,
            'body' => $story->body,
            'seo_title' => $story->seo_title,
            'seo_description' => $story->seo_description,
            'hero' => $this->media($story->heroMedia),
            'gallery' => $story->media
                ->map(fn (Media $media): array => $this->media($media, (int) $media->pivot->sort_order))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function media(?Media $media, ?int $sortOrder = null): ?array
    {
        if (! $media instanceof Media) {
            return null;
        }

        $payload = [
            'id' => $media->id,
            'filename' => $media->filename,
            'alt_text' => $media->alt_text,
            'mime_type' => $media->mime_type,
        ];

        if ($sortOrder !== null) {
            $payload['sort_order'] = $sortOrder;
        }

        return $payload;
    }
}
