<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Collection;
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
                ->with('heroMedia')
                ->where(function ($query) {
                    $query->whereHas('journalPosts', fn ($related) => $related->published())
                        ->orWhereHas('weddingStories', fn ($related) => $related->published());
                })
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
            'nearbyVenues' => $this->nearbyVenues($venue),
        ]);
    }

    /**
     * Up to 6 venues in the same city (then state, then region) so each
     * venue page links onward to related places couples are also weighing.
     */
    private function nearbyVenues(Venue $venue): Collection
    {
        $query = Venue::query()
            ->with('heroMedia')
            ->where('id', '!=', $venue->id)
            ->where(function ($q) use ($venue): void {
                if ($venue->city) {
                    $q->orWhere('city', $venue->city);
                }
                if ($venue->region) {
                    $q->orWhere('region', $venue->region);
                }
                if ($venue->state) {
                    $q->orWhere('state', $venue->state);
                }
            })
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->limit(6);

        if (! $venue->city && ! $venue->region && ! $venue->state) {
            return Venue::query()->whereRaw('1 = 0')->get();
        }

        return $query->get();
    }
}
