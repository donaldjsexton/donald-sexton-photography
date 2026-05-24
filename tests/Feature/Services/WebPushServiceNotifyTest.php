<?php

namespace Tests\Feature\Services;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPushServiceNotifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_skips_when_there_are_no_subscriptions(): void
    {
        $result = app(WebPushService::class)->notify('Title', 'Body');

        $this->assertSame('no_subscriptions', $result['skipped']);
        $this->assertSame(0, $result['total']);
    }

    public function test_notify_skips_when_vapid_keys_are_missing(): void
    {
        config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);

        PushSubscription::create([
            'user_id' => User::factory()->create()->id,
            'endpoint' => 'https://push.example.com/abc',
            'p256dh' => 'pub',
            'auth' => 'auth',
        ]);

        $result = app(WebPushService::class)->notify('Title', 'Body');

        $this->assertSame('missing_keys', $result['skipped']);
        $this->assertSame(1, $result['total']);
        $this->assertSame(0, $result['sent']);
    }
}
