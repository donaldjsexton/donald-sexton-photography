<?php

namespace App\Console\Commands\Gmail;

use App\Services\Gmail\GmailSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gmail:backfill {--days=90 : How many days of Gmail history to match}')]
#[Description('Initial Gmail backfill: match threads by inquiry email within the last N days.')]
class BackfillCommand extends Command
{
    public function handle(GmailSyncService $service): int
    {
        $days = max(1, (int) $this->option('days'));

        $this->info("Starting Gmail backfill ({$days} days)…");

        $result = $service->sync($days);

        $this->info(sprintf(
            'Backfill done: checked %d inquiries, linked %d threads, imported %d messages.',
            $result['checked'],
            $result['linked'],
            $result['new_messages'],
        ));

        return self::SUCCESS;
    }
}
