<?php

namespace App\Console\Commands\Gmail;

use App\Services\Gmail\GmailSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gmail:sync {--days=30 : Search window for unlinked inquiries}')]
#[Description('Incrementally sync Gmail threads into linked inquiries.')]
class SyncCommand extends Command
{
    public function handle(GmailSyncService $service): int
    {
        $days = max(1, (int) $this->option('days'));

        $result = $service->sync($days);

        $this->info(sprintf(
            'Gmail sync: checked %d inquiries, linked %d new threads, imported %d messages.',
            $result['checked'],
            $result['linked'],
            $result['new_messages'],
        ));

        return self::SUCCESS;
    }
}
