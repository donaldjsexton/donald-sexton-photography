<?php

namespace App\Mcp;

use App\Models\Media;
use App\Models\WeddingStory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DraftStoryService
{
    public function __construct(private readonly StoryPayload $payload) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        $story = new WeddingStory([
            'title' => $input['title'],
            'slug' => $this->uniqueSlug($input['slug'] ?? null, (string) $input['title']),
            'status' => 'draft',
            'story_type' => 'wedding',
            'excerpt' => $input['excerpt'] ?? null,
            'body' => $input['body'] ?? null,
            'seo_title' => $input['seo_title'] ?? null,
            'seo_description' => $input['seo_description'] ?? null,
            'published_at' => null,
            'is_featured' => false,
        ]);
        $story->save();

        return $this->payload->__invoke($story->fresh());
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(array $input): array
    {
        $story = $this->find((int) ($input['story_id'] ?? 0), $input['slug'] ?? null);
        $this->ensureDraft($story);

        foreach (['title', 'excerpt', 'body', 'seo_title', 'seo_description'] as $field) {
            if (array_key_exists($field, $input)) {
                $story->{$field} = $input[$field];
            }
        }

        if (array_key_exists('slug', $input) && filled($input['slug'])) {
            $story->slug = $this->uniqueSlug((string) $input['slug'], (string) $story->title, $story->id);
        }

        if (array_key_exists('gallery', $input)) {
            $heroId = array_key_exists('hero_media_id', $input)
                ? $input['hero_media_id']
                : $story->hero_media_id;
            $this->syncGallery($story, $input['gallery'], $heroId);
        }

        if (array_key_exists('hero_media_id', $input)) {
            $this->assignHero($story, $input['hero_media_id']);
        }

        $story->status = 'draft';
        $story->save();

        return $this->payload->__invoke($story->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function publish(int $storyId): array
    {
        $story = $this->find($storyId, null);

        if ($story->status === 'published') {
            return $this->payload->__invoke($story);
        }

        $this->ensureDraft($story);

        $story->status = 'published';
        $story->published_at = $story->published_at ?? now();
        $story->save();

        return $this->payload->__invoke($story->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function lookup(?int $storyId, ?string $slug): array
    {
        return $this->payload->__invoke($this->find($storyId ?? 0, $slug));
    }

    private function find(int $storyId, ?string $slug): WeddingStory
    {
        $query = WeddingStory::query();

        if ($storyId > 0) {
            $story = $query->whereKey($storyId)->first();
        } elseif (filled($slug)) {
            $story = $query->where('slug', $slug)->first();
        } else {
            $story = null;
        }

        if (! $story instanceof WeddingStory) {
            throw ValidationException::withMessages([
                'story' => 'Story was not found.',
            ]);
        }

        return $story;
    }

    private function ensureDraft(WeddingStory $story): void
    {
        if ($story->status === 'draft') {
            return;
        }

        throw ValidationException::withMessages([
            'story' => 'Only a draft can be changed here. Publishing is a separate operation.',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $gallery
     */
    private function syncGallery(WeddingStory $story, array $gallery, mixed $heroId): void
    {
        $ids = array_map(fn (array $item): int => (int) $item['media_id'], $gallery);

        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'gallery' => 'Gallery media ids must be unique.',
            ]);
        }

        $found = Media::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if (count($found) !== count($ids)) {
            throw ValidationException::withMessages([
                'gallery' => 'One or more gallery images were not found.',
            ]);
        }

        if ($heroId !== null && ! in_array((int) $heroId, $ids, true)) {
            throw ValidationException::withMessages([
                'hero_media_id' => 'The hero image must be one of the ordered gallery images.',
            ]);
        }

        $sync = [];

        foreach ($ids as $index => $id) {
            $sync[$id] = [
                'role' => 'gallery',
                'sort_order' => $index,
            ];
        }

        $story->media()->sync($sync);

        foreach ($gallery as $item) {
            if (! array_key_exists('alt_text', $item)) {
                continue;
            }

            Media::query()->whereKey((int) $item['media_id'])->update([
                'alt_text' => $item['alt_text'],
            ]);
        }
    }

    private function assignHero(WeddingStory $story, mixed $heroId): void
    {
        if ($heroId === null) {
            $story->hero_media_id = null;

            return;
        }

        $heroId = (int) $heroId;
        $attached = $story->media()->whereKey($heroId)->exists();

        if (! $attached) {
            throw ValidationException::withMessages([
                'hero_media_id' => 'The hero image must be one of the ordered gallery images.',
            ]);
        }

        $story->hero_media_id = $heroId;
    }

    private function uniqueSlug(?string $preferred, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug(filled($preferred) ? (string) $preferred : $title);
        $base = $base !== '' ? $base : 'story';
        $slug = $base;
        $suffix = 2;

        while (WeddingStory::query()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
