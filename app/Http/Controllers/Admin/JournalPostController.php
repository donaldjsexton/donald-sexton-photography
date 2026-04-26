<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\JournalPost;
use App\Models\Tag;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JournalPostController extends Controller
{
    public function index(): View
    {
        return view('admin.journal-posts.index', [
            'posts' => JournalPost::query()->latest()->paginate(24),
        ]);
    }

    public function create(): View
    {
        return view('admin.journal-posts.form', $this->formData(new JournalPost([
            'status' => 'draft',
            'post_type' => 'advice',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePost($request);
        $post = new JournalPost;
        $this->fillPost($post, $validated);
        $this->syncRelations($post, $validated);

        return redirect()
            ->route('admin.journal-posts.edit', $post)
            ->with('status', 'Journal post created.');
    }

    public function edit(JournalPost $journalPost): View
    {
        $journalPost->loadMissing('heroMedia');

        return view('admin.journal-posts.form', $this->formData($journalPost));
    }

    public function update(Request $request, JournalPost $journalPost): RedirectResponse
    {
        $validated = $this->validatePost($request, $journalPost);
        $this->fillPost($journalPost, $validated);
        $this->syncRelations($journalPost, $validated);

        return redirect()
            ->route('admin.journal-posts.edit', $journalPost)
            ->with('status', 'Journal post updated.');
    }

    private function validatePost(Request $request, ?JournalPost $post = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('journal_posts', 'slug')->ignore($post?->id)],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'post_type' => ['required', Rule::in(['advice', 'venue', 'real_wedding', 'engagement', 'brand', 'announcement'])],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'hero_media_id' => ['nullable', 'integer', 'exists:media,id'],
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
        ]);
    }

    private function fillPost(JournalPost $post, array $validated): void
    {
        $post->fill($validated);
        $post->slug = $validated['slug'] ?: Str::slug($validated['title']);
        $post->save();
    }

    private function syncRelations(JournalPost $post, array $validated): void
    {
        $post->categories()->sync($validated['category_ids'] ?? []);
        $post->tags()->sync($validated['tag_ids'] ?? []);
        $post->venues()->sync($validated['venue_ids'] ?? []);
    }

    private function formData(JournalPost $post): array
    {
        return [
            'post' => $post,
            'categories' => Category::query()->orderBy('name')->get(),
            'tags' => Tag::query()->orderBy('name')->get(),
            'venues' => Venue::query()->orderBy('name')->get(),
            'postTypes' => ['advice', 'venue', 'real_wedding', 'engagement', 'brand', 'announcement'],
            'statuses' => ['draft', 'published', 'archived'],
        ];
    }
}
