<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Block;
use App\Models\Media;
use App\Services\Cms\BlockManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait ManagesBlocks
{
    abstract protected function blocksEditorRedirect(string $status, Model $owner): RedirectResponse;

    protected function createBlock(Request $request, Model $owner): RedirectResponse
    {
        $blocks = app(BlockManager::class);
        $blocks->create($owner, $request->validate($blocks->rules()));

        return $this->blocksEditorRedirect('Block added.', $owner);
    }

    protected function modifyBlock(Request $request, Model $owner, Block $block): RedirectResponse
    {
        $this->authorizeBlock($owner, $block);

        $blocks = app(BlockManager::class);
        $blocks->update($block, $request->validate($blocks->rules()));

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

        app(BlockManager::class)->reorder($owner, $validated['block_ids']);

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

    private function authorizeBlock(Model $owner, Block $block): void
    {
        abort_unless(app(BlockManager::class)->owns($owner, $block), 404);
    }
}
