<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Tenancy\CurrentSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VenueController extends Controller
{
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
        $validated = $this->validateVenue($request);
        $venue = new Venue;
        $this->fillVenue($venue, $validated);

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
        $validated = $this->validateVenue($request, $venue);
        $this->fillVenue($venue, $validated);

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

    /**
     * @return array<string, mixed>
     */
    private function validateVenue(Request $request, ?Venue $venue = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('venues', 'slug')->where('site_id', app(CurrentSite::class)->id())->ignore($venue?->id)],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'hero_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'google_places_id' => ['nullable', 'string', 'max:255'],
            'referral_emails' => ['nullable', 'string'],
            'referral_contact_name' => ['nullable', 'string', 'max:255'],
            'is_featured' => ['nullable', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],

            'business_name' => ['nullable', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_contact_name' => ['nullable', 'string', 'max:255'],
            'billing_address_line_1' => ['nullable', 'string', 'max:255'],
            'billing_address_line_2' => ['nullable', 'string', 'max:255'],
            'billing_city' => ['nullable', 'string', 'max:255'],
            'billing_state' => ['nullable', 'string', 'max:100'],
            'billing_postal_code' => ['nullable', 'string', 'max:20'],
            'billing_country' => ['nullable', 'string', 'size:2'],
            'net_payment_terms' => ['nullable', 'string', 'max:50'],
            'portal_password' => ['nullable', 'string', 'min:8'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function fillVenue(Venue $venue, array $validated): void
    {
        $portalPassword = $validated['portal_password'] ?? null;
        unset($validated['portal_password']);

        $venue->fill($validated);
        $venue->slug = ! empty($validated['slug']) ? $validated['slug'] : Str::slug($validated['name']);
        $venue->is_featured = (bool) ($validated['is_featured'] ?? false);
        $venue->referral_emails = $this->parseReferralEmails($validated['referral_emails'] ?? null);

        if (filled($portalPassword)) {
            $venue->password = $portalPassword;
        }

        $venue->save();
    }

    /**
     * @return array<int, string>|null
     */
    private function parseReferralEmails(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $emails = collect(preg_split('/[\s,;]+/', $raw))
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();

        return $emails === [] ? null : $emails;
    }
}
