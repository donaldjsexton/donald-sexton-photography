<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Minishlink\WebPush\VAPID;
use Tests\TestCase;

class PushTestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_missing_keys_and_prints_a_generated_keypair(): void
    {
        config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);

        $this->artisan('push:test')
            ->expectsOutputToContain('VAPID keys are not configured')
            ->expectsOutputToContain('VAPID_PUBLIC_KEY=')
            ->expectsOutputToContain('VAPID_PRIVATE_KEY=')
            ->assertExitCode(0);
    }

    public function test_generate_option_prints_a_keypair(): void
    {
        $this->artisan('push:test --generate')
            ->expectsOutputToContain('VAPID_PUBLIC_KEY=')
            ->assertExitCode(0);
    }

    public function test_reports_no_subscriptions_when_keys_configured_but_none_stored(): void
    {
        $keys = VAPID::createVapidKeys();
        config([
            'services.webpush.public_key' => $keys['publicKey'],
            'services.webpush.private_key' => $keys['privateKey'],
        ]);

        $this->artisan('push:test')
            ->expectsOutputToContain('VAPID keys are configured.')
            ->expectsOutputToContain('No subscriptions yet')
            ->assertExitCode(0);
    }
}
