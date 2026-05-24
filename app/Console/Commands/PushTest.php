<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

#[Signature('push:test {--generate : Print a fresh VAPID keypair instead of sending}')]
#[Description('Diagnose web push: report VAPID config and stored subscriptions, then send a test notification.')]
class PushTest extends Command
{
    public function handle(WebPushService $webPush): int
    {
        if ($this->option('generate')) {
            return $this->printKeypair();
        }

        $publicKey = WebPushService::normalizeKey(config('services.webpush.public_key'));
        $privateKey = WebPushService::normalizeKey(config('services.webpush.private_key'));

        if ($publicKey === null || $privateKey === null) {
            $this->error('VAPID keys are not configured. Set VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY in your .env.');
            $this->newLine();

            return $this->printKeypair();
        }

        $this->info('VAPID keys are configured.');

        $count = PushSubscription::query()->count();
        $this->line("Stored subscriptions: {$count}");

        if ($count === 0) {
            $this->warn('No subscriptions yet. Open the admin and click "Enable notifications" first.');

            return self::SUCCESS;
        }

        $this->line('Sending test notification…');

        $result = $webPush->notify(
            'Test notification',
            'If you can read this, web push is working.',
            route('admin.dashboard'),
        );

        if ($result['skipped'] !== null) {
            $this->error("Skipped: {$result['skipped']}. Check the application log for details.");

            return self::FAILURE;
        }

        $this->info(sprintf('Done. %d sent, %d failed (of %d).', $result['sent'], $result['failed'], $result['total']));

        if ($result['failed'] > 0) {
            $this->warn('Some deliveries failed — see the application log for status codes and reasons.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function printKeypair(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line('Generated a VAPID keypair. Add these to your .env, then clear config and redeploy:');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();

        return self::SUCCESS;
    }
}
