<?php

namespace App\Console\Commands;

use App\Models\Venue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

#[Signature('venue:import-google
    {query? : Search query, e.g. "wedding venues in Tampa FL"}
    {--region=* : Shorthand regions to search (clearwater, tampa, st-pete, sarasota, gulf-coast)}
    {--dry-run : Show what would be imported without saving}
')]
#[Description('Import wedding venues from Google Places API into the venues table.')]
class VenueImportGoogleCommand extends Command
{
    /** @var array<string, string> */
    private const REGION_QUERIES = [
        'clearwater' => 'wedding venues in Clearwater FL',
        'tampa' => 'wedding venues in Tampa FL',
        'st-pete' => 'wedding venues in St Petersburg FL',
        'sarasota' => 'wedding venues in Sarasota FL',
        'gulf-coast' => 'wedding venues Florida Gulf Coast',
        'orlando' => 'wedding venues in Orlando FL',
        'lakeland' => 'wedding venues in Lakeland FL',
    ];

    private const PLACE_FIELDS = [
        'places.id',
        'places.displayName',
        'places.formattedAddress',
        'places.addressComponents',
        'places.websiteUri',
        'places.googleMapsUri',
    ];

    public function handle(): int
    {
        $apiKey = config('services.google.places_api_key');

        if (! $apiKey) {
            $this->error('GOOGLE_PLACES_API_KEY is not set. Add it to your .env file.');

            return self::FAILURE;
        }

        $queries = $this->buildQueries();

        if (empty($queries)) {
            $this->error('Provide a search query argument or use --region flags.');
            $this->line('  Available regions: '.implode(', ', array_keys(self::REGION_QUERIES)));

            return self::FAILURE;
        }

        $isDryRun = $this->option('dry-run');
        $created = 0;
        $skipped = 0;

        foreach ($queries as $query) {
            $this->info("Searching: {$query}");

            $places = $this->searchPlaces($apiKey, $query);

            if (empty($places)) {
                $this->warn('  No results.');

                continue;
            }

            $this->line(sprintf('  Found %d places.', count($places)));

            foreach ($places as $place) {
                $parsed = $this->parsePlace($place);

                if (! $parsed) {
                    continue;
                }

                if (Venue::query()->where('google_places_id', $parsed['google_places_id'])->exists()) {
                    $skipped++;

                    continue;
                }

                if (Venue::query()->where('name', $parsed['name'])->where('city', $parsed['city'])->exists()) {
                    $skipped++;

                    continue;
                }

                if ($isDryRun) {
                    $this->line(sprintf(
                        '  [dry-run] %s — %s, %s',
                        $parsed['name'],
                        $parsed['city'] ?? '?',
                        $parsed['state'] ?? '?',
                    ));
                    $created++;

                    continue;
                }

                Venue::create($parsed);
                $created++;
                $this->line(sprintf('  + %s (%s, %s)', $parsed['name'], $parsed['city'] ?? '?', $parsed['state'] ?? '?'));
            }
        }

        $this->newLine();
        $label = $isDryRun ? 'Would import' : 'Imported';
        $this->info("{$label} {$created} venues, skipped {$skipped} duplicates.");

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function buildQueries(): array
    {
        if ($query = $this->argument('query')) {
            return [$query];
        }

        $regions = $this->option('region');

        if (empty($regions)) {
            $regions = array_keys(self::REGION_QUERIES);
        }

        $queries = [];

        foreach ($regions as $region) {
            if (isset(self::REGION_QUERIES[$region])) {
                $queries[] = self::REGION_QUERIES[$region];
            } else {
                $queries[] = "wedding venues in {$region}";
            }
        }

        return $queries;
    }

    /** @return list<array<string, mixed>> */
    private function searchPlaces(string $apiKey, string $query): array
    {
        $allPlaces = [];
        $pageToken = null;

        do {
            $body = ['textQuery' => $query];

            if ($pageToken) {
                $body['pageToken'] = $pageToken;
            }

            $response = Http::withHeaders([
                'X-Goog-Api-Key' => $apiKey,
                'X-Goog-FieldMask' => implode(',', self::PLACE_FIELDS).',nextPageToken',
            ])
                ->timeout(15)
                ->retry(2, 500)
                ->post('https://places.googleapis.com/v1/places:searchText', $body);

            if ($response->failed()) {
                $this->warn('  API error: '.$response->status().' — '.$response->body());

                break;
            }

            $data = $response->json();
            $places = $data['places'] ?? [];
            $allPlaces = array_merge($allPlaces, $places);
            $pageToken = $data['nextPageToken'] ?? null;

            if ($pageToken) {
                usleep(300_000);
            }
        } while ($pageToken);

        return $allPlaces;
    }

    /**
     * @param  array<string, mixed>  $place
     * @return array<string, mixed>|null
     */
    private function parsePlace(array $place): ?array
    {
        $name = $place['displayName']['text'] ?? null;

        if (! $name) {
            return null;
        }

        $city = null;
        $state = null;

        foreach ($place['addressComponents'] ?? [] as $component) {
            $types = $component['types'] ?? [];

            if (in_array('locality', $types, true)) {
                $city = $component['longText'] ?? $component['shortText'] ?? null;
            }

            if (in_array('administrative_area_level_1', $types, true)) {
                $state = $component['shortText'] ?? null;
            }
        }

        $region = $this->resolveRegion($city);

        $slug = Str::slug($name);

        $suffix = 1;
        $candidate = $slug;
        while (Venue::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.++$suffix;
        }

        return [
            'name' => $name,
            'slug' => $candidate,
            'city' => $city,
            'state' => $state,
            'region' => $region,
            'website_url' => $place['websiteUri'] ?? null,
            'google_places_id' => $place['id'] ?? null,
        ];
    }

    private function resolveRegion(?string $city): ?string
    {
        if (! $city) {
            return null;
        }

        $cityLower = strtolower($city);

        $regionMap = [
            'clearwater' => 'Clearwater',
            'clearwater beach' => 'Clearwater',
            'dunedin' => 'Clearwater',
            'safety harbor' => 'Clearwater',
            'palm harbor' => 'Clearwater',
            'largo' => 'Clearwater',
            'tampa' => 'Tampa',
            'brandon' => 'Tampa',
            'riverview' => 'Tampa',
            'lutz' => 'Tampa',
            'wesley chapel' => 'Tampa',
            'st petersburg' => 'St. Petersburg',
            'st. petersburg' => 'St. Petersburg',
            'gulfport' => 'St. Petersburg',
            'treasure island' => 'St. Petersburg',
            'sarasota' => 'Sarasota',
            'bradenton' => 'Sarasota',
            'longboat key' => 'Sarasota',
            'siesta key' => 'Sarasota',
            'lakewood ranch' => 'Sarasota',
            'orlando' => 'Orlando',
            'winter park' => 'Orlando',
            'lakeland' => 'Lakeland',
        ];

        return $regionMap[$cityLower] ?? 'Florida';
    }
}
