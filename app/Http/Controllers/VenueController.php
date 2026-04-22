<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VenueController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->toString();

        $venues = Venue::query()
            ->when($term !== '', fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($term).'%']))
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'name', 'city', 'state']);

        return response()->json($venues);
    }

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
