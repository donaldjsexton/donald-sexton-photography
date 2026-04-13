<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageSetting;
use App\Models\JournalPost;
use App\Models\Media;
use App\Models\Testimonial;
use App\Models\WeddingStory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomepageSettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.homepage.edit', [
            'settings' => HomepageSetting::query()->first() ?? new HomepageSetting,
            'mediaItems' => Media::query()->latest()->limit(250)->get(['id', 'filename']),
            'stories' => WeddingStory::query()->latest()->limit(250)->get(['id', 'title']),
            'journalPosts' => JournalPost::query()->latest()->limit(250)->get(['id', 'title']),
            'testimonials' => Testimonial::query()->orderByDesc('is_featured')->orderBy('sort_order')->get(['id', 'author_name', 'is_featured', 'sort_order']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
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
        ]);

        $settings = HomepageSetting::query()->first() ?? new HomepageSetting;
        $settings->fill($validated);
        $settings->save();

        return redirect()
            ->route('admin.homepage.edit')
            ->with('status', 'Homepage settings updated.');
    }
}
