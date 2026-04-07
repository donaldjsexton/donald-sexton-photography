<?php

namespace App\Http\Controllers;

use App\Models\WeddingStory;
use Illuminate\View\View;

class WeddingStoryController extends Controller
{
    public function index(): View
    {
        return view('weddings.index', [
            'stories' => WeddingStory::published()
                ->with(['heroMedia', 'venue'])
                ->orderByRaw('CASE WHEN published_at IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(12),
        ]);
    }

    public function show(string $slug): View
    {
        $story = WeddingStory::published()
            ->with(['heroMedia', 'venue', 'storyBlocks', 'tags', 'media'])
            ->where('slug', $slug)
            ->firstOrFail();

        return view('weddings.show', [
            'story' => $story,
        ]);
    }
}
