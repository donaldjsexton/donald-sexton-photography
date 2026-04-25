<?php

namespace App\Console\Commands\Gmail;

use App\Services\VenueReferral\VenueReferralIngestor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gmail:ingest-venue-referrals {--days=14 : Search window in days for venue referral emails}')]
#[Description('Scan Gmail for venue referral emails, extract couple details, and auto-send intro replies when confidence is high.')]
class IngestVenueReferralsCommand extends Command
{
    public function handle(VenueReferralIngestor $ingestor): int
    {
        $days = max(1, (int) $this->option('days'));

        $result = $ingestor->ingest($days);

        $this->info(sprintf(
            'Venue referrals: checked %d, created %d (auto-sent %d, queued for review %d).',
            $result['checked'],
            $result['created'],
            $result['auto_sent'],
            $result['queued_for_review'],
        ));

        return self::SUCCESS;
    }
}
