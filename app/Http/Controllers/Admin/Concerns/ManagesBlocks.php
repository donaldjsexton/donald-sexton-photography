<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Block;
use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

trait ManagesBlocks
{
    abstract protected function blocksEditorRedirect(string $status, Model $owner): RedirectResponse;

    protected function createBlock(Request $request, Model $owner): RedirectResponse
    {
        $validated = $this->validateBlock($request);

        $owner->allBlocks()->create([
            'type' => $validated['type'],
            'heading' => $validated['heading'] ?? null,
            'subheading' => $validated['subheading'] ?? null,
            'body' => $validated['body'] ?? null,
            'data' => $this->cleanBlockData($validated['data'] ?? null),
            'is_visible' => (bool) ($validated['is_visible'] ?? true),
            'sort_order' => $validated['sort_order'] ?? $this->nextBlockSortOrder($owner),
        ]);

        return $this->blocksEditorRedirect('Block added.', $owner);
    }

    protected function modifyBlock(Request $request, Model $owner, Block $block): RedirectResponse
    {
        $this->authorizeBlock($owner, $block);

        $validated = $this->validateBlock($request);

        $block->update([
            'type' => $validated['type'],
            'heading' => $validated['heading'] ?? null,
            'subheading' => $validated['subheading'] ?? null,
            'body' => $validated['body'] ?? null,
            'data' => $this->cleanBlockData($validated['data'] ?? null),
            'is_visible' => (bool) ($validated['is_visible'] ?? false),
            'sort_order' => $validated['sort_order'] ?? $block->sort_order,
        ]);

        return $this->blocksEditorRedirect('Block updated.', $owner);
    }

    protected function removeBlock(Model $owner, Block $block): RedirectResponse
    {
        $this->authorizeBlock($owner, $block);

        $block->media()->detach();
        $block->delete();

        return $this->blocksEditorRedirect('Block removed.', $owner);
    }

    protected function reorderBlocks(Request $request, Model $owner): Response
    {
        $validated = $request->validate([
            'block_ids' => ['required', 'array'],
            'block_ids.*' => ['integer'],
        ]);

        $ownedIds = $owner->allBlocks()->pluck('id')->all();
        $position = 0;

        foreach ($validated['block_ids'] as $id) {
            $id = (int) $id;

            if (! in_array($id, $ownedIds, true)) {
                continue;
            }

            Block::whereKey($id)->update(['sort_order' => $position++]);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->noContent();
        }

        return $this->blocksEditorRedirect('Order updated.', $owner);
    }

    protected function attachBlockMedia(Request $request, Model $owner, Block $block): RedirectResponse
    {
        $this->authorizeBlock($owner, $block);

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

        return $this->blocksEditorRedirect('Image attached.', $owner);
    }

    protected function detachBlockMedia(Model $owner, Block $block, Media $media): RedirectResponse
    {
        $this->authorizeBlock($owner, $block);

        $block->media()->detach($media->id);

        return $this->blocksEditorRedirect('Image removed.', $owner);
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
    private function cleanBlockData(?array $data): ?array
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

    private function nextBlockSortOrder(Model $owner): int
    {
        return (int) $owner->allBlocks()->max('sort_order') + 1;
    }

    private function authorizeBlock(Model $owner, Block $block): void
    {
        abort_unless(
            $block->blockable_type === $owner->getMorphClass() && (int) $block->blockable_id === (int) $owner->getKey(),
            404,
        );
    }
}
