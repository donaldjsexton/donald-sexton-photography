<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\View\View;

class VenueController extends Controller
{
    public function index(): View
    {
        return view('venues.index', [
            'venues' => Venue::query()
                ->withCount([
                    'weddingStories' => fn ($query) => $query->published(),
                    'journalPosts' => fn ($query) => $query->published(),
                ])
                ->orderByDesc('is_featured')
                ->orderBy('name')
                ->paginate(12),
        ]);
    }

    public function show(string $slug): View
    {
        $venue = Venue::query()
            ->with('heroMedia')
            ->where('slug', $slug)
            ->firstOrFail();

        return view('venues.show', [
            'venue' => $venue,
            'stories' => $venue->weddingStories()
                ->published()
                ->with('heroMedia')
                ->latest('published_at')
                ->get(),
            'posts' => $venue->journalPosts()
                ->published()
                ->with('heroMedia')
                ->latest('published_at')
                ->get(),
        ]);
    }
}
