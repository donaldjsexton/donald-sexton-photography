<?php

namespace App\Console\Commands\Calendar;

use App\Services\CalendarSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('calendar:sync')]
#[Description('Sync booked wedding jobs from Google Calendar.')]
class SyncCommand extends Command
{
    public function handle(CalendarSync $service): int
    {
        $count = $service->sync();

        $this->info("Calendar sync: upserted {$count} booked jobs.");

        return self::SUCCESS;
    }
}
