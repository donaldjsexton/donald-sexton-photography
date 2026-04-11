<?php

namespace Tests\Feature\Services;

use App\Models\Inquiry;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Services\MarketingHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MarketingHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_returns_expected_shape(): void
    {
        $snapshot = app(MarketingHealth::class)->snapshot(new SiteSetting);

        $this->assertSame(['signals', 'seoCoverage', 'attribution'], array_keys($snapshot));
        $this->assertCount(4, $snapshot['signals']);
        $this->assertCount(3, $snapshot['seoCoverage']);
        $this->assertSame([], $snapshot['attribution']);
    }

    public function test_ga4_signal_toggles_tone_based_on_site_settings(): void
    {
        $service = app(MarketingHealth::class);

        $missing = collect($service->snapshot(new SiteSetting)['signals'])
            ->firstWhere('label', 'GA4 Analytics');
        $this->assertSame('Missing', $missing['value']);
        $this->assertSame('warning', $missing['tone']);

        $configured = new SiteSetting(['google_analytics_measurement_id' => 'G-TEST123']);
        $connected = collect($service->snapshot($configured)['signals'])
            ->firstWhere('label', 'GA4 Analytics');
        $this->assertSame('Connected', $connected['value']);
        $this->assertSame('positive', $connected['tone']);
    }

    public function test_seo_coverage_reports_percentage_of_records_with_title_and_description(): void
    {
        Page::create([
            'title' => 'Covered',
            'slug' => 'covered',
            'status' => 'published',
            'seo_title' => 'Covered | DSP',
            'seo_description' => 'A covered page.',
        ]);
        Page::create([
            'title' => 'Bare',
            'slug' => 'bare',
            'status' => 'published',
        ]);

        $coverage = collect(app(MarketingHealth::class)->snapshot(new SiteSetting)['seoCoverage'])
            ->keyBy('label');

        $this->assertSame('50%', $coverage['Pages']['value']);
        $this->assertSame('1 of 2 records have title and description.', $coverage['Pages']['context']);
        $this->assertSame('0%', $coverage['Wedding Stories']['value']);
        $this->assertSame('0%', $coverage['Journal Posts']['value']);
    }

    public function test_attribution_ranks_recent_utm_sources_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15 10:00:00'));

        $this->makeInquiry(['utm_source' => 'google'], now()->subDays(5));
        $this->makeInquiry(['utm_source' => 'google'], now()->subDays(10));
        $this->makeInquiry(['utm_source' => 'instagram'], now()->subDays(30));
        $this->makeInquiry(['utm_source' => 'google'], now()->subDays(200));
        $this->makeInquiry(['utm_source' => null]);

        $attribution = app(MarketingHealth::class)->snapshot(new SiteSetting)['attribution'];

        $this->assertCount(2, $attribution);
        $this->assertSame('Google', $attribution[0]['label']);
        $this->assertSame('2 tagged inquiries in the last 90 days.', $attribution[0]['meta']);
        $this->assertSame('Instagram', $attribution[1]['label']);

        Carbon::setTestNow();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeInquiry(array $overrides = [], ?Carbon $createdAt = null): Inquiry
    {
        $inquiry = Inquiry::create(array_merge([
            'primary_name' => 'Taylor',
            'email' => 'taylor+'.uniqid().'@example.com',
            'status' => 'new',
            'source' => 'site_form',
        ], $overrides));

        if ($createdAt !== null) {
            Inquiry::withoutTimestamps(function () use ($inquiry, $createdAt) {
                $inquiry->forceFill(['created_at' => $createdAt])->save();
            });
        }

        return $inquiry;
    }
}
