<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesBlocks;
use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\HomepageSetting;
use App\Models\Media;
use App\Support\HomepageBlocksSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomepageBlockController extends Controller
{
    use ManagesBlocks;

    public function seed(): RedirectResponse
    {
        $created = HomepageBlocksSeeder::seed();

        return $this->blocksEditorRedirect(
            $created > 0 ? 'Homepage rebuilt from default sections.' : 'Homepage already uses blocks.',
            $this->owner(),
        );
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->createBlock($request, $this->owner());
    }

    public function update(Request $request, Block $block): RedirectResponse
    {
        return $this->modifyBlock($request, $this->owner(), $block);
    }

    public function destroy(Block $block): RedirectResponse
    {
        return $this->removeBlock($this->owner(), $block);
    }

    public function attachMedia(Request $request, Block $block): RedirectResponse
    {
        return $this->attachBlockMedia($request, $this->owner(), $block);
    }

    public function detachMedia(Block $block, Media $media): RedirectResponse
    {
        return $this->detachBlockMedia($this->owner(), $block, $media);
    }

    private function owner(): HomepageSetting
    {
        return HomepageSetting::query()->firstOrCreate([]);
    }

    protected function blocksEditorRedirect(string $status, Model $owner): RedirectResponse
    {
        return redirect()
            ->route('admin.homepage.edit')
            ->with('status', $status);
    }
}
