<?php

namespace App\Services\Cms;

use App\Models\JournalPost;
use App\Tenancy\CurrentSite;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class JournalPostManager
{
    /**
     * @return list<string>
     */
    public static function postTypes(): array
    {
        return ['advice', 'venue', 'real_wedding', 'engagement', 'brand', 'announcement'];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return ['draft', 'published', 'archived'];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(?JournalPost $post = null): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('journal_posts', 'slug')->where('site_id', app(CurrentSite::class)->id())->ignore($post?->id)],
            'status' => ['required', Rule::in(self::statuses())],
            'post_type' => ['required', Rule::in(self::postTypes())],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'hero_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'gallery_id' => ['nullable', Rule::exists('galleries', 'id')->where('site_id', app(CurrentSite::class)->id())],
            'author_name' => ['nullable', 'string', 'max:255'],
            'published_at' => ['nullable', 'date'],
            'original_wp_post_id' => ['nullable', 'integer'],
            'original_wp_url' => ['nullable', 'url', 'max:255'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'venue_ids' => ['nullable', 'array'],
            'venue_ids.*' => ['integer', 'exists:venues,id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function save(JournalPost $post, array $validated, bool $partial = false): void
    {
        $post->fill($validated);

        if (! $partial || array_key_exists('slug', $validated)) {
            $post->slug = ($validated['slug'] ?? null) ?: Str::slug($validated['title'] ?? $post->title);
        }

        $post->save();

        foreach (['category_ids' => 'categories', 'tag_ids' => 'tags', 'venue_ids' => 'venues'] as $key => $relation) {
            if (! $partial || array_key_exists($key, $validated)) {
                $post->{$relation}()->sync($validated[$key] ?? []);
            }
        }
    }
}
