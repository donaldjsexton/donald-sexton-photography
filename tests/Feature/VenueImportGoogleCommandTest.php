<?php

namespace Tests\Feature;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VenueImportGoogleCommandTest extends TestCase
{
    use RefreshDatabase;

    private function fakePlacesResponse(array $places = [], ?string $nextPageToken = null): array
    {
        $response = ['places' => $places];

        if ($nextPageToken) {
            $response['nextPageToken'] = $nextPageToken;
        }

        return $response;
    }

    private function fakePlace(string $name, string $city = 'Tampa', string $state = 'FL', ?string $id = null): array
    {
        return [
            'id' => $id ?? 'ChIJ_'.fake()->unique()->bothify('##??##??##'),
            'displayName' => ['text' => $name],
            'formattedAddress' => "{$name}, {$city}, {$state}",
            'addressComponents' => [
                ['types' => ['locality'], 'longText' => $city, 'shortText' => $city],
                ['types' => ['administrative_area_level_1'], 'longText' => 'Florida', 'shortText' => $state],
            ],
            'websiteUri' => 'https://example.com',
        ];
    }

    public function test_fails_without_api_key(): void
    {
        config(['services.google.places_api_key' => null]);

        $this->artisan('venue:import-google', ['query' => 'wedding venues Tampa'])
            ->assertFailed()
            ->expectsOutputToContain('GOOGLE_PLACES_API_KEY');
    }

    public function test_fails_without_query_or_region(): void
    {
        config(['services.google.places_api_key' => 'test-key']);

        Http::fake(['*' => Http::response($this->fakePlacesResponse([
            $this->fakePlace('Venue One'),
        ]))]);

        // Without arguments it uses all default regions, so it should succeed
        $this->artisan('venue:import-google')
            ->assertSuccessful();
    }

    public function test_imports_venues_from_api(): void
    {
        config(['services.google.places_api_key' => 'test-key']);

        Http::fake([
            'places.googleapis.com/*' => Http::response($this->fakePlacesResponse([
                $this->fakePlace('The Birchwood', 'St. Petersburg', 'FL', 'ChIJ_birchwood'),
                $this->fakePlace('Armature Works', 'Tampa', 'FL', 'ChIJ_armature'),
            ])),
        ]);

        $this->artisan('venue:import-google', ['query' => 'wedding venues Tampa'])
            ->assertSuccessful();

        $this->assertDatabaseHas('venues', [
            'name' => 'The Birchwood',
            'city' => 'St. Petersburg',
            'google_places_id' => 'ChIJ_birchwood',
        ]);

        $this->assertDatabaseHas('venues', [
            'name' => 'Armature Works',
            'city' => 'Tampa',
            'google_places_id' => 'ChIJ_armature',
        ]);
    }

    public function test_skips_duplicate_google_places_id(): void
    {
        config(['services.google.places_api_key' => 'test-key']);

        Venue::factory()->create([
            'name' => 'Existing Venue',
            'google_places_id' => 'ChIJ_existing',
        ]);

        Http::fake([
            'places.googleapis.com/*' => Http::response($this->fakePlacesResponse([
                $this->fakePlace('Existing Venue', 'Tampa', 'FL', 'ChIJ_existing'),
                $this->fakePlace('New Venue', 'Tampa', 'FL', 'ChIJ_new'),
            ])),
        ]);

        $this->artisan('venue:import-google', ['query' => 'wedding venues Tampa'])
            ->assertSuccessful()
            ->expectsOutputToContain('skipped 1');

        $this->assertDatabaseCount('venues', 2);
    }

    public function test_rerun_does_not_create_duplicate_venues(): void
    {
        config(['services.google.places_api_key' => 'test-key']);

        Http::fake([
            'places.googleapis.com/*' => Http::response($this->fakePlacesResponse([
                $this->fakePlace('Repeatable Venue', 'Tampa', 'FL', 'ChIJ_repeat'),
            ])),
        ]);

        $this->artisan('venue:import-google', ['query' => 'wedding venues Tampa'])->assertSuccessful();
        $this->artisan('venue:import-google', ['query' => 'wedding venues Tampa'])
            ->assertSuccessful()
            ->expectsOutputToContain('skipped 1');

        $this->assertDatabaseCount('venues', 1);
    }

    public function test_dry_run_does_not_persist(): void
    {
        config(['services.google.places_api_key' => 'test-key']);

        Http::fake([
            'places.googleapis.com/*' => Http::response($this->fakePlacesResponse([
                $this->fakePlace('Dry Run Venue', 'Tampa', 'FL', 'ChIJ_dryrun'),
            ])),
        ]);

        $this->artisan('venue:import-google', ['query' => 'test', '--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('dry-run');

        $this->assertDatabaseMissing('venues', ['name' => 'Dry Run Venue']);
    }

    public function test_resolves_region_from_city(): void
    {
        config(['services.google.places_api_key' => 'test-key']);

        Http::fake([
            'places.googleapis.com/*' => Http::response($this->fakePlacesResponse([
                $this->fakePlace('Safety Harbor Resort', 'Safety Harbor', 'FL', 'ChIJ_safety'),
            ])),
        ]);

        $this->artisan('venue:import-google', ['query' => 'test'])
            ->assertSuccessful();

        $this->assertDatabaseHas('venues', [
            'name' => 'Safety Harbor Resort',
            'region' => 'Clearwater',
        ]);
    }

    public function test_generates_unique_slugs(): void
    {
        config(['services.google.places_api_key' => 'test-key']);

        Venue::factory()->create(['name' => 'The Venue', 'slug' => 'the-venue']);

        Http::fake([
            'places.googleapis.com/*' => Http::response($this->fakePlacesResponse([
                $this->fakePlace('The Venue', 'Sarasota', 'FL', 'ChIJ_venue2'),
            ])),
        ]);

        $this->artisan('venue:import-google', ['query' => 'test'])
            ->assertSuccessful();

        $this->assertDatabaseHas('venues', [
            'google_places_id' => 'ChIJ_venue2',
            'slug' => 'the-venue-2',
        ]);
    }
}
