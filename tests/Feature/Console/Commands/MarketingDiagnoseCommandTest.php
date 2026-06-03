<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Venue;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketingDiagnoseCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_coverage_inventory_and_thin_pages(): void
    {
        Http::fake();

        WeddingStory::create([
            'title' => 'Complete Story',
            'slug' => 'complete-story',
            'status' => 'published',
            'hero_media_id' => null,
            'seo_title' => 'Title',
            'seo_description' => 'Description.',
            'body' => '<p>'.str_repeat('word ', 120).'</p>',
        ]);

        $thin = WeddingStory::create([
            'title' => 'Thin Story',
            'slug' => 'thin-story',
            'status' => 'published',
            'body' => '<p>Too short.</p>',
        ]);

        Venue::factory()->create(['name' => 'Lonely Venue', 'hero_media_id' => null]);

        $this->artisan('marketing:diagnose')
            ->expectsOutputToContain('SEO metadata coverage')
            ->expectsOutputToContain('Content inventory')
            ->expectsOutputToContain('Thin Story')
            ->expectsOutputToContain('Lonely Venue')
            ->assertSuccessful();

        // No Google connection in tests -> Search Console is never queried.
        Http::assertNothingSent();
        $this->assertNotNull($thin->fresh());
    }

    public function test_json_flag_outputs_machine_readable_report(): void
    {
        Http::fake();

        Venue::factory()->create();

        $exitCode = Artisan::call('marketing:diagnose', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"seoCoverage"', $output);
        $this->assertStringContainsString('"inventory"', $output);
    }
}
