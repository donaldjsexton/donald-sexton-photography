<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Models\Venue;
use App\Models\WeddingStory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WeddingStoryController extends Controller
{
    public function index(): View
    {
        return view('admin.wedding-stories.index', [
            'stories' => WeddingStory::query()->with('venue')->latest()->paginate(24),
        ]);
    }

    public function create(): View
    {
        return view('admin.wedding-stories.form', $this->formData(new WeddingStory([
            'status' => 'draft',
            'story_type' => 'wedding',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateStory($request);
        $story = new WeddingStory;
        $this->fillStory($story, $validated);
        $story->tags()->sync($validated['tag_ids'] ?? []);

        return redirect()
            ->route('admin.wedding-stories.edit', $story)
            ->with('status', 'Wedding story created.');
    }

    public function edit(WeddingStory $weddingStory): View
    {
        $weddingStory->loadMissing('heroMedia');

        return view('admin.wedding-stories.form', $this->formData($weddingStory));
    }

    public function update(Request $request, WeddingStory $weddingStory): RedirectResponse
    {
        $validated = $this->validateStory($request, $weddingStory);
        $this->fillStory($weddingStory, $validated);
        $weddingStory->tags()->sync($validated['tag_ids'] ?? []);

        return redirect()
            ->route('admin.wedding-stories.edit', $weddingStory)
            ->with('status', 'Wedding story updated.');
    }

    private function validateStory(Request $request, ?WeddingStory $story = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('wedding_stories', 'slug')->ignore($story?->id)],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'story_type' => ['required', Rule::in(['wedding', 'elopement', 'engagement', 'editorial'])],
            'headline' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'hero_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'event_date' => ['nullable', 'date'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'client_names' => ['nullable', 'string'],
            'is_featured' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'published_at' => ['nullable', 'date'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);
    }

    private function fillStory(WeddingStory $story, array $validated): void
    {
        $story->fill($validated);
        $story->slug = $validated['slug'] ?: Str::slug($validated['title']);
        $story->client_names = $this->parseClientNames($validated['client_names'] ?? null);
        $story->is_featured = (bool) ($validated['is_featured'] ?? false);
        $story->display_order = $validated['display_order'] ?? 0;
        $story->save();
    }

    private function parseClientNames(?string $value): ?array
    {
        $names = collect(explode(',', (string) $value))
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->values()
            ->all();

        return $names === [] ? null : $names;
    }

    private function formData(WeddingStory $story): array
    {
        return [
            'story' => $story,
            'venues' => Venue::query()->orderBy('name')->get(),
            'tags' => Tag::query()->orderBy('name')->get(),
            'storyTypes' => ['wedding', 'elopement', 'engagement', 'editorial'],
            'statuses' => ['draft', 'published', 'archived'],
        ];
    }
}
