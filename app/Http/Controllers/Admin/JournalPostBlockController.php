<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesBlocks;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\JournalPost;
use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JournalPostBlockController extends Controller
{
    use ManagesBlocks;

    public function store(Request $request, JournalPost $journalPost): RedirectResponse
    {
        return $this->createBlock($request, $journalPost);
    }

    public function update(Request $request, JournalPost $journalPost, Block $block): RedirectResponse
    {
        return $this->modifyBlock($request, $journalPost, $block);
    }

    public function destroy(JournalPost $journalPost, Block $block): RedirectResponse
    {
        return $this->removeBlock($journalPost, $block);
    }

    public function reorder(Request $request, JournalPost $journalPost): Response
    {
        return $this->reorderBlocks($request, $journalPost);
    }

    public function attachMedia(Request $request, JournalPost $journalPost, Block $block): RedirectResponse
    {
        return $this->attachBlockMedia($request, $journalPost, $block);
    }

    public function detachMedia(JournalPost $journalPost, Block $block, Media $media): RedirectResponse
    {
        return $this->detachBlockMedia($journalPost, $block, $media);
    }

    protected function blocksEditorRedirect(string $status, Model $owner): RedirectResponse
    {
        return redirect()
            ->route('admin.journal-posts.edit', $owner)
            ->with('status', $status);
    }
}
