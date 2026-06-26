<?php

namespace App\Http\Controllers;

use App\Models\Redirect;
use App\Models\WeddingStory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

    public function show(string $slug): View|RedirectResponse
    {
        $story = WeddingStory::published()
            ->with(['heroMedia', 'venue', 'blocks.media', 'tags', 'media', 'clientGallery'])
            ->where('slug', $slug)
            ->first();

        if (! $story) {
            $redirect = Redirect::query()->whereIn('from_path', [
                '/weddings/'.$slug,
                '/weddings/'.$slug.'/',
            ])->first();

            if ($redirect) {
                return redirect()->to($redirect->to_path, $redirect->status_code);
            }

            throw new NotFoundHttpException;
        }

        return view('weddings.show', [
            'story' => $story,
            'relatedStories' => WeddingStory::similarTo($story, 3),
            'relatedPosts' => WeddingStory::relatedPostsTo($story, 3),
        ]);
    }
}
