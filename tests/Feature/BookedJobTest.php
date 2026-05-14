<?php

namespace Tests\Feature;

use App\Models\BookedJob;
use App\Models\User;
use App\Services\CalendarSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class BookedJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.booked-jobs.index'));

        $response->assertOk();
        $response->assertSee('Calendar');
    }

    public function test_calendar_page_redirects_guests(): void
    {
        $response = $this->get(route('admin.booked-jobs.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_calendar_displays_jobs_for_given_month(): void
    {
        $user = User::factory()->create();

        $job = BookedJob::factory()->create([
            'event_date' => '2026-06-15',
            'couple_names' => 'Sarah & James',
        ]);

        $response = $this->actingAs($user)->get(route('admin.booked-jobs.index', [
            'year' => 2026,
            'month' => 6,
        ]));

        $response->assertOk();
        $response->assertSee('Sarah & James');
        $response->assertSee('June 2026');
    }

    public function test_show_page_displays_booked_job_details(): void
    {
        $user = User::factory()->create();

        $job = BookedJob::factory()->create([
            'couple_names' => 'Emily & David',
            'location' => 'The Breakers',
            'ceremony_notes' => 'Sunset ceremony on the lawn.',
        ]);

        $response = $this->actingAs($user)->get(route('admin.booked-jobs.show', $job));

        $response->assertOk();
        $response->assertSee('Emily & David');
        $response->assertSee('The Breakers');
        $response->assertSee('Sunset ceremony on the lawn.');
    }

    public function test_admin_can_update_booked_job(): void
    {
        $user = User::factory()->create();

        $job = BookedJob::factory()->create([
            'couple_names' => 'Old Names',
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($user)->put(route('admin.booked-jobs.update', $job), [
            'couple_names' => 'New Names',
            'event_time' => '5:00 PM',
            'location' => 'Flagler Museum',
            'coordinator' => 'FL Destination Weddings',
            'ceremony_notes' => 'Updated notes.',
            'status' => 'completed',
        ]);

        $response->assertRedirect(route('admin.booked-jobs.show', $job));
        $response->assertSessionHas('status', 'Booked job updated.');

        $job->refresh();
        $this->assertSame('New Names', $job->couple_names);
        $this->assertSame('completed', $job->status);
        $this->assertSame('Flagler Museum', $job->location);
    }

    public function test_update_validates_status(): void
    {
        $user = User::factory()->create();
        $job = BookedJob::factory()->create();

        $response = $this->actingAs($user)->put(route('admin.booked-jobs.update', $job), [
            'status' => 'invalid_status',
        ]);

        $response->assertSessionHasErrors('status');
    }

    public function test_upcoming_scope_returns_future_confirmed_jobs(): void
    {
        BookedJob::factory()->create([
            'event_date' => now()->addDays(10),
            'status' => 'confirmed',
        ]);

        BookedJob::factory()->create([
            'event_date' => now()->subDays(5),
            'status' => 'confirmed',
        ]);

        BookedJob::factory()->create([
            'event_date' => now()->addDays(3),
            'status' => 'cancelled',
        ]);

        $upcoming = BookedJob::upcoming()->get();

        $this->assertCount(1, $upcoming);
    }

    public function test_in_month_scope_filters_correctly(): void
    {
        BookedJob::factory()->create(['event_date' => '2026-07-15']);
        BookedJob::factory()->create(['event_date' => '2026-08-01']);

        $julyJobs = BookedJob::inMonth(2026, 7)->get();

        $this->assertCount(1, $julyJobs);
    }

    public function test_calendar_page_triggers_on_demand_sync_when_throttle_is_clear(): void
    {
        Cache::forget('calendar:sync:throttle');
        $user = User::factory()->create();

        $sync = Mockery::mock(CalendarSync::class);
        $sync->shouldReceive('sync')->once()->andReturn(0);
        $this->app->instance(CalendarSync::class, $sync);

        $this->actingAs($user)->get(route('admin.booked-jobs.index'))->assertOk();
    }

    public function test_calendar_page_does_not_resync_within_throttle_window(): void
    {
        Cache::forget('calendar:sync:throttle');
        $user = User::factory()->create();

        $sync = Mockery::mock(CalendarSync::class);
        $sync->shouldReceive('sync')->once()->andReturn(0);
        $this->app->instance(CalendarSync::class, $sync);

        $this->actingAs($user)->get(route('admin.booked-jobs.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.booked-jobs.index'))->assertOk();
    }

    public function test_calendar_page_renders_when_sync_throws(): void
    {
        Cache::forget('calendar:sync:throttle');
        $user = User::factory()->create();

        $sync = Mockery::mock(CalendarSync::class);
        $sync->shouldReceive('sync')->once()->andThrow(new \RuntimeException('Google down'));
        $this->app->instance(CalendarSync::class, $sync);

        $this->actingAs($user)->get(route('admin.booked-jobs.index'))->assertOk();
    }
}
