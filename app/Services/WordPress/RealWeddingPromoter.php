<?php

namespace App\Services\WordPress;

use App\Models\ImportMapping;
use App\Models\JournalPost;
use App\Models\Redirect;
use App\Models\WeddingStory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RealWeddingPromoter
{
    public function promote(JournalPost $post): WeddingStory
    {
        return DB::transaction(function () use ($post): WeddingStory {
            $post->loadMissing(['tags', 'media']);

            $story = $post->original_wp_post_id
                ? WeddingStory::query()->firstOrNew(['original_wp_post_id' => $post->original_wp_post_id])
                : WeddingStory::query()->firstOrNew(['slug' => $post->slug]);

            $story->fill([
                'title' => $post->title,
                'slug' => $this->uniqueStorySlug($post->slug, $story->id),
                'status' => $post->status,
                'story_type' => $this->resolveStoryType($post->post_type),
                'headline' => $post->title,
                'excerpt' => $post->excerpt,
                'body' => $post->body,
                'hero_media_id' => $post->hero_media_id,
                'seo_title' => $post->seo_title,
                'seo_description' => $post->seo_description,
                'canonical_url' => $post->canonical_url,
                'original_wp_post_id' => $post->original_wp_post_id,
                'original_wp_url' => $post->original_wp_url,
                'published_at' => $post->published_at,
            ]);
            $story->save();

            $story->tags()->sync($post->tags->modelKeys());

            $media = $post->media
                ->mapWithKeys(fn ($media) => [
                    $media->id => [
                        'role' => (string) ($media->pivot->role ?? 'gallery'),
                        'sort_order' => (int) ($media->pivot->sort_order ?? 0),
                    ],
                ])
                ->all();

            if ($media !== []) {
                $story->media()->sync($media);
            }

            Redirect::query()->updateOrCreate(
                ['from_path' => '/journal/'.$post->slug],
                [
                    'to_path' => '/weddings/'.$story->slug,
                    'status_code' => 301,
                    'source' => 'wp_import',
                ]
            );

            if ($post->original_wp_url) {
                $this->syncOriginalRedirect($post->original_wp_url, '/weddings/'.$story->slug);
            }

            if ($post->original_wp_post_id) {
                ImportMapping::query()
                    ->where('source_table', 'wp_posts')
                    ->where('source_id', $post->original_wp_post_id)
                    ->update([
                        'target_type' => $story->getMorphClass(),
                        'target_id' => $story->id,
                    ]);
            }

            if ($post->status !== 'archived') {
                $post->update(['status' => 'archived']);
            }

            return $story;
        });
    }

    private function uniqueStorySlug(string $slug, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug) ?: 'wedding-story';
        $candidate = $base;
        $suffix = 2;

        while (
            WeddingStory::query()
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    private function resolveStoryType(string $postType): string
    {
        return match ($postType) {
            'engagement' => 'engagement',
            default => 'wedding',
        };
    }

    private function syncOriginalRedirect(string $sourceUrl, string $toPath): void
    {
        $fromPath = parse_url($sourceUrl, PHP_URL_PATH);

        if (! is_string($fromPath) || trim($fromPath) === '') {
            return;
        }

        Redirect::query()->updateOrCreate(
            ['from_path' => '/'.ltrim($fromPath, '/')],
            [
                'to_path' => $toPath,
                'status_code' => 301,
                'source' => 'wp_import',
            ]
        );
    }
}
