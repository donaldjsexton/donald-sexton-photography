<?php

namespace Tests\Feature\Services;

use App\Models\Inquiry;
use App\Services\CrmMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CrmMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pulse_returns_expected_shape_with_no_data(): void
    {
        $pulse = app(CrmMetrics::class)->pulse();

        $this->assertSame(['stats', 'funnel', 'topSources'], array_keys($pulse));
        $this->assertCount(4, $pulse['stats']);
        $this->assertCount(count(Inquiry::statusOptions()), $pulse['funnel']);
        $this->assertSame([], $pulse['topSources']);

        $newThisWeek = collect($pulse['stats'])->firstWhere('label', 'New This Week');
        $this->assertSame('0', $newThisWeek['value']);
    }

    public function test_pulse_counts_inquiries_for_the_current_week_and_pipeline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15 10:00:00'));

        $this->makeInquiry(['status' => 'new'], now()->subDay());
        $this->makeInquiry(['status' => 'active'], now()->subDay());
        $this->makeInquiry(['status' => 'follow_up'], now()->subDay());
        $this->makeInquiry(['status' => 'booked'], now()->subMonths(2));
        $this->makeInquiry(['status' => 'archived'], now()->subDay());
        $this->makeInquiry(['status' => 'new'], now()->subWeeks(2));

        $pulse = app(CrmMetrics::class)->pulse();
        $stats = collect($pulse['stats'])->keyBy('label');

        $this->assertSame('4', $stats['New This Week']['value'], 'All inquiries created this week regardless of status.');
        $this->assertSame('4', $stats['Active Pipeline']['value'], 'new + active + follow_up (any time).');
        $this->assertSame('1', $stats['Booked YTD']['value']);

        Carbon::setTestNow();
    }

    public function test_pulse_funnel_uses_status_options_ordering_and_counts(): void
    {
        $this->makeInquiry(['status' => 'new']);
        $this->makeInquiry(['status' => 'new']);
        $this->makeInquiry(['status' => 'booked']);

        $funnel = app(CrmMetrics::class)->pulse()['funnel'];

        $labels = array_column($funnel, 'label');
        $this->assertSame(array_values(Inquiry::statusOptions()), $labels);

        $counts = collect($funnel)->keyBy('label');
        $newLabel = Inquiry::statusOptions()['new'];
        $bookedLabel = Inquiry::statusOptions()['booked'];

        $this->assertSame(2, $counts[$newLabel]['value']);
        $this->assertSame(1, $counts[$bookedLabel]['value']);
    }

    public function test_pulse_top_sources_ranks_most_common_first(): void
    {
        $this->makeInquiry(['source' => 'site_form']);
        $this->makeInquiry(['source' => 'site_form']);
        $this->makeInquiry(['source' => 'site_form']);
        $this->makeInquiry(['source' => 'referral']);

        $topSources = app(CrmMetrics::class)->pulse()['topSources'];

        $this->assertCount(2, $topSources);
        $this->assertSame('Site Form', $topSources[0]['label']);
        $this->assertSame('3 inquiries tracked.', $topSources[0]['meta']);
        $this->assertSame('Referral', $topSources[1]['label']);
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
