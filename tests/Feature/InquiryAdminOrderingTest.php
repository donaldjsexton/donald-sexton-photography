<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InquiryAdminOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ordered_scope_sorts_by_event_date_within_status_groups(): void
    {
        $newLater = Inquiry::factory()->create([
            'primary_name' => 'New Later',
            'status' => 'new',
            'event_date' => '2027-08-01',
        ]);

        $newEarlier = Inquiry::factory()->create([
            'primary_name' => 'New Earlier',
            'status' => 'new',
            'event_date' => '2026-12-01',
        ]);

        $newNoDate = Inquiry::factory()->create([
            'primary_name' => 'New No Date',
            'status' => 'new',
            'event_date' => null,
        ]);

        $bookedSoon = Inquiry::factory()->booked()->create([
            'primary_name' => 'Booked Soon',
            'event_date' => '2026-06-15',
        ]);

        $ordered = Inquiry::query()->adminOrdered()->pluck('id')->all();

        $this->assertSame(
            [$newEarlier->id, $newLater->id, $newNoDate->id, $bookedSoon->id],
            $ordered,
        );
    }

    public function test_admin_ordered_scope_falls_back_to_created_at_when_event_dates_match(): void
    {
        $first = Inquiry::factory()->create([
            'status' => 'new',
            'event_date' => '2026-09-01',
            'created_at' => now()->subDay(),
        ]);

        $second = Inquiry::factory()->create([
            'status' => 'new',
            'event_date' => '2026-09-01',
            'created_at' => now(),
        ]);

        $ordered = Inquiry::query()->adminOrdered()->pluck('id')->all();

        $this->assertSame([$second->id, $first->id], $ordered);
    }
}
