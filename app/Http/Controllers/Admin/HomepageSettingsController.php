<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageSetting;
use App\Models\JournalPost;
use App\Models\Testimonial;
use App\Models\WeddingStory;
use App\Services\Cms\HomepageSettingsManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomepageSettingsController extends Controller
{
    public function __construct(private readonly HomepageSettingsManager $settings) {}

    public function edit(): View
    {
        $settings = HomepageSetting::query()->with(['heroMedia', 'allBlocks.media'])->first() ?? new HomepageSetting;

        return view('admin.homepage.edit', [
            'settings' => $settings,
            'stories' => WeddingStory::query()->latest()->limit(250)->get(['id', 'title']),
            'journalPosts' => JournalPost::query()->latest()->limit(250)->get(['id', 'title']),
            'testimonials' => Testimonial::query()->orderByDesc('is_featured')->orderBy('sort_order')->get(['id', 'author_name', 'is_featured', 'sort_order']),
            'homeBlocks' => $settings->exists ? $settings->allBlocks : collect(),
            'blockTypes' => $this->blockTypes(),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function blockTypes(): array
    {
        return array_filter(
            (array) config('blocks.types'),
            fn (array $definition): bool => ($definition['context'] ?? 'page') === 'homepage',
        );
    }

    public function update(Request $request): RedirectResponse
    {
        $this->settings->save($request->validate($this->settings->rules()));

        return redirect()
            ->route('admin.homepage.edit')
            ->with('status', 'Homepage settings updated.');
    }
}
