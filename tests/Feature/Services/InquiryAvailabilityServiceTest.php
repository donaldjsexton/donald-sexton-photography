<?php

namespace Tests\Feature\Services;

use App\Models\BookedJob;
use App\Services\InquiryAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InquiryAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2027-01-15'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_returns_unknown_when_no_event_date_provided(): void
    {
        $result = (new InquiryAvailabilityService)->forDate(null);

        $this->assertSame('unknown', $result['status']);
        $this->assertNull($result['event_date']);
        $this->assertSame([], $result['nearby_dates']);
    }

    public function test_returns_available_when_no_confirmed_booking_exists(): void
    {
        $result = (new InquiryAvailabilityService)->forDate(Carbon::parse('2027-06-12'));

        $this->assertSame('available', $result['status']);
        $this->assertSame('2027-06-12', $result['event_date']->toDateString());
        $this->assertSame([], $result['nearby_dates']);
    }

    public function test_cancelled_job_does_not_block_availability(): void
    {
        BookedJob::factory()->cancelled()->create([
            'event_date' => '2027-06-12',
        ]);

        $result = (new InquiryAvailabilityService)->forDate(Carbon::parse('2027-06-12'));

        $this->assertSame('available', $result['status']);
    }

    public function test_returns_unavailable_with_nearby_saturdays_when_date_is_booked(): void
    {
        BookedJob::factory()->create([
            'event_date' => '2027-06-12',
            'status' => 'confirmed',
        ]);

        $result = (new InquiryAvailabilityService)->forDate(Carbon::parse('2027-06-12'));

        $this->assertSame('unavailable', $result['status']);
        $this->assertNotEmpty($result['nearby_dates']);

        foreach ($result['nearby_dates'] as $date) {
            $this->assertTrue($date->isSaturday(), 'Suggested dates should be Saturdays.');
            $this->assertTrue($date->isFuture());
        }
    }

    public function test_unavailable_suggestions_skip_other_booked_saturdays(): void
    {
        BookedJob::factory()->create(['event_date' => '2027-06-12', 'status' => 'confirmed']);
        BookedJob::factory()->create(['event_date' => '2027-06-19', 'status' => 'confirmed']);
        BookedJob::factory()->create(['event_date' => '2027-06-05', 'status' => 'confirmed']);

        $result = (new InquiryAvailabilityService)->forDate(Carbon::parse('2027-06-12'));

        $suggestedDates = collect($result['nearby_dates'])->map->toDateString()->all();

        $this->assertNotContains('2027-06-19', $suggestedDates);
        $this->assertNotContains('2027-06-05', $suggestedDates);
    }

    public function test_past_event_dates_are_returned_as_unknown(): void
    {
        $result = (new InquiryAvailabilityService)->forDate(Carbon::parse('2026-01-01'));

        $this->assertSame('unknown', $result['status']);
    }
}
