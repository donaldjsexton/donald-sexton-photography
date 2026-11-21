<?php

namespace App\Services\Cms;

use App\Models\Page;
use App\Tenancy\CurrentSite;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageManager
{
    /**
     * @return list<string>
     */
    public static function templates(): array
    {
        return ['home', 'about', 'collections', 'faq', 'location', 'custom'];
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
    public function rules(?Page $page = null): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('pages', 'slug')->where('site_id', app(CurrentSite::class)->id())->ignore($page?->id)],
            'template' => ['required', Rule::in(self::templates())],
            'status' => ['required', Rule::in(self::statuses())],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'hero_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'published_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'faqs_text' => ['nullable', 'string', 'max:8000'],
        ];
    }

    /**
     * Fill and save a page. A partial save only touches the keys that were
     * sent, so remote clients can change one field at a time.
     *
     * @param  array<string, mixed>  $validated
     */
    public function save(Page $page, array $validated, bool $partial = false): void
    {
        $faqsText = $validated['faqs_text'] ?? null;
        unset($validated['faqs_text']);

        $page->fill($validated);

        if (! $partial || array_key_exists('slug', $validated)) {
            $page->slug = ($validated['slug'] ?? null) ?: Str::slug($validated['title'] ?? $page->title);
        }

        if (! $partial || array_key_exists('sort_order', $validated)) {
            $page->sort_order = $validated['sort_order'] ?? 0;
        }

        if (! $partial || $faqsText !== null) {
            $page->faqs = FaqText::parse($faqsText);
        }

        $page->save();
    }
}
