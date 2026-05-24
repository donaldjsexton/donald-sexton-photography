<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesBlocks;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Media;
use App\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PageBlockController extends Controller
{
    use ManagesBlocks;

    public function store(Request $request, Page $page): RedirectResponse
    {
        return $this->createBlock($request, $page);
    }

    public function update(Request $request, Page $page, Block $block): RedirectResponse
    {
        return $this->modifyBlock($request, $page, $block);
    }

    public function destroy(Page $page, Block $block): RedirectResponse
    {
        return $this->removeBlock($page, $block);
    }

    public function reorder(Request $request, Page $page): Response
    {
        return $this->reorderBlocks($request, $page);
    }

    public function attachMedia(Request $request, Page $page, Block $block): RedirectResponse
    {
        return $this->attachBlockMedia($request, $page, $block);
    }

    public function detachMedia(Page $page, Block $block, Media $media): RedirectResponse
    {
        return $this->detachBlockMedia($page, $block, $media);
    }

    protected function blocksEditorRedirect(string $status, Model $owner): RedirectResponse
    {
        return redirect()
            ->route('admin.pages.edit', $owner)
            ->with('status', $status);
    }
}
