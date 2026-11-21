<?php

namespace App\Services\Cms;

use App\Models\HomepageSetting;

class HomepageSettingsManager
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hero_heading' => ['nullable', 'string', 'max:255'],
            'hero_subheading' => ['nullable', 'string'],
            'hero_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'featured_story_ids_json' => ['nullable', 'array'],
            'featured_story_ids_json.*' => ['integer', 'exists:wedding_stories,id'],
            'featured_testimonial_ids_json' => ['nullable', 'array'],
            'featured_testimonial_ids_json.*' => ['integer', 'exists:testimonials,id'],
            'featured_journal_post_ids_json' => ['nullable', 'array'],
            'featured_journal_post_ids_json.*' => ['integer', 'exists:journal_posts,id'],
            'investment_teaser' => ['nullable', 'string'],
            'final_cta_heading' => ['nullable', 'string', 'max:255'],
            'final_cta_body' => ['nullable', 'string'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function save(array $validated): HomepageSetting
    {
        $settings = HomepageSetting::query()->first() ?? new HomepageSetting;
        $settings->fill($validated);
        $settings->save();

        return $settings;
    }
}
