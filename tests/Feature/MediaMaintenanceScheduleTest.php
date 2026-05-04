<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class MediaMaintenanceScheduleTest extends TestCase
{
    public function test_media_optimize_is_scheduled_monthly_with_webp_backfill_flags(): void
    {
        $event = $this->scheduledEventMatching('media:optimize');

        $this->assertNotNull($event, 'Expected media:optimize to be scheduled.');
        $this->assertStringContainsString('--generate-webp', $event->command);
        $this->assertStringContainsString('--only-missing-webp', $event->command);
        $this->assertSame('0 3 1 * *', $event->expression);
    }

    public function test_media_generate_variants_is_scheduled_monthly(): void
    {
        $event = $this->scheduledEventMatching('media:generate-variants');

        $this->assertNotNull($event, 'Expected media:generate-variants to be scheduled.');
        $this->assertSame('0 4 1 * *', $event->expression);
    }

    private function scheduledEventMatching(string $needle): ?Event
    {
        $schedule = $this->app->make(Schedule::class);

        foreach ($schedule->events() as $event) {
            if (str_contains((string) $event->command, $needle)) {
                return $event;
            }
        }

        return null;
    }
}
