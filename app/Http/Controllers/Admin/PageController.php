<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Tenancy\CurrentSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        return view('admin.pages.index', [
            'pages' => Page::query()->latest()->paginate(24),
        ]);
    }

    public function create(): View
    {
        return view('admin.pages.form', [
            'page' => new Page(['status' => 'draft', 'template' => 'custom']),
            'templates' => $this->templates(),
            'statuses' => $this->statuses(),
            'blockTypes' => $this->blockTypes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePage($request);
        $page = new Page;
        $this->fillPage($page, $validated);

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('status', 'Page created.');
    }

    public function edit(Page $page): View
    {
        $page->loadMissing(['heroMedia', 'allBlocks.media']);

        return view('admin.pages.form', [
            'page' => $page,
            'templates' => $this->templates(),
            'statuses' => $this->statuses(),
            'blockTypes' => $this->blockTypes(),
        ]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $validated = $this->validatePage($request, $page);
        $this->fillPage($page, $validated);

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('status', 'Page updated.');
    }

    private function validatePage(Request $request, ?Page $page = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('pages', 'slug')->where('site_id', app(CurrentSite::class)->id())->ignore($page?->id)],
            'template' => ['required', Rule::in($this->templates())],
            'status' => ['required', Rule::in($this->statuses())],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'hero_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'published_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function fillPage(Page $page, array $validated): void
    {
        $page->fill($validated);
        $page->slug = $validated['slug'] ?: Str::slug($validated['title']);
        $page->sort_order = $validated['sort_order'] ?? 0;
        $page->save();
    }

    private function templates(): array
    {
        return ['home', 'about', 'collections', 'faq', 'location', 'custom'];
    }

    private function statuses(): array
    {
        return ['draft', 'published', 'archived'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function blockTypes(): array
    {
        return array_filter(
            (array) config('blocks.types'),
            fn (array $definition): bool => ($definition['context'] ?? 'page') !== 'homepage',
        );
    }
}
