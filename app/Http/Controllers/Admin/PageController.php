<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Cms\PageManager;
use App\Support\LocationPageDraft;
use App\Tenancy\CurrentSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(private readonly PageManager $pages) {}

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
        $validated = $request->validate($this->pages->rules());
        $page = new Page;
        $this->pages->save($page, $validated);

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
        $validated = $request->validate($this->pages->rules($page));
        $this->pages->save($page, $validated);

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('status', 'Page updated.');
    }

    public function generateLocation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'city' => ['required', 'string', 'max:128'],
            'state' => ['nullable', 'string', 'max:64'],
            'region' => ['nullable', 'string', 'max:128'],
        ]);

        $draft = LocationPageDraft::build(
            city: trim($validated['city']),
            state: trim((string) ($validated['state'] ?? '')) ?: null,
            region: trim((string) ($validated['region'] ?? '')) ?: null,
        );

        $slug = $this->resolveLocationSlug($draft->slug);

        $page = new Page;
        $page->fill([
            'title' => $draft->title,
            'slug' => $slug,
            'template' => 'location',
            'status' => 'draft',
            'excerpt' => $draft->excerpt,
            'body' => $draft->body,
            'seo_title' => $draft->seoTitle,
            'seo_description' => $draft->seoDescription,
            'faqs' => $draft->faqs,
        ]);
        $page->sort_order = 0;
        $page->save();

        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('status', 'Location page drafted from "'.$draft->city.'". Add a hero image, review the copy, and publish when ready.');
    }

    private function resolveLocationSlug(string $candidate): string
    {
        $siteId = app(CurrentSite::class)->id();
        $base = $candidate;
        $slug = $base;
        $suffix = 2;

        while (Page::query()
            ->where('site_id', $siteId)
            ->where('slug', $slug)
            ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function templates(): array
    {
        return PageManager::templates();
    }

    private function statuses(): array
    {
        return PageManager::statuses();
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
