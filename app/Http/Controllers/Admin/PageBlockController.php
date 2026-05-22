<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Media;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PageBlockController extends Controller
{
    public function store(Request $request, Page $page): RedirectResponse
    {
        $validated = $this->validateBlock($request);

        $page->allBlocks()->create([
            'type' => $validated['type'],
            'heading' => $validated['heading'] ?? null,
            'subheading' => $validated['subheading'] ?? null,
            'body' => $validated['body'] ?? null,
            'data' => $this->cleanData($validated['data'] ?? null),
            'is_visible' => (bool) ($validated['is_visible'] ?? true),
            'sort_order' => $validated['sort_order'] ?? $this->nextSortOrder($page),
        ]);

        return $this->backToEdit($page, 'Block added.');
    }

    public function update(Request $request, Page $page, Block $block): RedirectResponse
    {
        $this->authorizeBlock($page, $block);

        $validated = $this->validateBlock($request);

        $block->update([
            'type' => $validated['type'],
            'heading' => $validated['heading'] ?? null,
            'subheading' => $validated['subheading'] ?? null,
            'body' => $validated['body'] ?? null,
            'data' => $this->cleanData($validated['data'] ?? null),
            'is_visible' => (bool) ($validated['is_visible'] ?? false),
            'sort_order' => $validated['sort_order'] ?? $block->sort_order,
        ]);

        return $this->backToEdit($page, 'Block updated.');
    }

    public function destroy(Page $page, Block $block): RedirectResponse
    {
        $this->authorizeBlock($page, $block);

        $block->media()->detach();
        $block->delete();

        return $this->backToEdit($page, 'Block removed.');
    }

    public function attachMedia(Request $request, Page $page, Block $block): RedirectResponse
    {
        $this->authorizeBlock($page, $block);

        $validated = $request->validate([
            'media_id' => ['required', 'integer', 'exists:media,id'],
        ]);

        $mediaId = (int) $validated['media_id'];

        if (! $block->media()->whereKey($mediaId)->exists()) {
            $nextSort = (int) $block->media()->max('mediables.sort_order');

            $block->media()->attach($mediaId, [
                'role' => 'gallery',
                'sort_order' => $block->media()->count() === 0 ? 0 : $nextSort + 1,
            ]);
        }

        return $this->backToEdit($page, 'Image attached.');
    }

    public function detachMedia(Page $page, Block $block, Media $media): RedirectResponse
    {
        $this->authorizeBlock($page, $block);

        $block->media()->detach($media->id);

        return $this->backToEdit($page, 'Image removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBlock(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(array_keys((array) config('blocks.types')))],
            'heading' => ['nullable', 'string', 'max:255'],
            'subheading' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
            'is_visible' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>|null
     */
    private function cleanData(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $filtered = array_filter(
            $data,
            fn ($value) => $value !== null && $value !== '',
        );

        return $filtered === [] ? null : $filtered;
    }

    private function nextSortOrder(Page $page): int
    {
        return (int) $page->allBlocks()->max('sort_order') + 1;
    }

    private function authorizeBlock(Page $page, Block $block): void
    {
        abort_unless(
            $block->blockable_type === $page->getMorphClass() && (int) $block->blockable_id === $page->id,
            404,
        );
    }

    private function backToEdit(Page $page, string $status): RedirectResponse
    {
        return redirect()
            ->route('admin.pages.edit', $page)
            ->with('status', $status);
    }
}
