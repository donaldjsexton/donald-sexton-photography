<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Services\Cms\VenueManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VenueController extends Controller
{
    public function __construct(private readonly VenueManager $venues) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $venues = Venue::query()
            ->withCount(['weddingStories', 'journalPosts'])
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query->where(function ($q) use ($like) {
                    $q->where('name', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhere('state', 'like', $like)
                        ->orWhere('region', 'like', $like);
                });
            })
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        return view('admin.venues.index', [
            'venues' => $venues,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.venues.form', [
            'venue' => new Venue(['is_featured' => false]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->venues->rules());
        $venue = new Venue;
        $this->venues->save($venue, $validated);

        return redirect()
            ->route('admin.venues.edit', $venue)
            ->with('status', 'Venue created.');
    }

    public function edit(Venue $venue): View
    {
        $venue->loadMissing('heroMedia');

        return view('admin.venues.form', [
            'venue' => $venue,
        ]);
    }

    public function update(Request $request, Venue $venue): RedirectResponse
    {
        $validated = $request->validate($this->venues->rules($venue));
        $this->venues->save($venue, $validated);

        return redirect()
            ->route('admin.venues.edit', $venue)
            ->with('status', 'Venue updated.');
    }

    public function destroy(Venue $venue): RedirectResponse
    {
        $venue->delete();

        return redirect()
            ->route('admin.venues.index')
            ->with('status', 'Venue deleted.');
    }
}
