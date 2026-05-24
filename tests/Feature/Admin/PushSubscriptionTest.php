<?php

namespace Tests\Feature\Admin;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscribe_requires_authentication(): void
    {
        $this->postJson('/admin/push/subscribe', [
            'endpoint' => 'https://push.example.com/abc',
            'keys' => ['p256dh' => 'pub', 'auth' => 'secret'],
        ])->assertUnauthorized();
    }

    public function test_subscribe_stores_subscription_for_user(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->postJson('/admin/push/subscribe', [
            'endpoint' => 'https://push.example.com/abc',
            'keys' => ['p256dh' => 'pub-key', 'auth' => 'auth-key'],
        ])->assertOk()->assertJson(['subscribed' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $admin->id,
            'endpoint' => 'https://push.example.com/abc',
            'p256dh' => 'pub-key',
            'auth' => 'auth-key',
        ]);
    }

    public function test_subscribe_is_idempotent_per_endpoint(): void
    {
        $admin = User::factory()->create();
        $payload = [
            'endpoint' => 'https://push.example.com/abc',
            'keys' => ['p256dh' => 'pub-key', 'auth' => 'auth-key'],
        ];

        $this->actingAs($admin)->postJson('/admin/push/subscribe', $payload)->assertOk();
        $this->actingAs($admin)->postJson('/admin/push/subscribe', $payload)->assertOk();

        $this->assertSame(1, PushSubscription::query()->where('endpoint', $payload['endpoint'])->count());
    }

    public function test_subscribe_validates_keys(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->postJson('/admin/push/subscribe', [
            'endpoint' => 'https://push.example.com/abc',
        ])->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
    }

    public function test_unsubscribe_removes_only_callers_subscription(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create();

        $mine = PushSubscription::create([
            'user_id' => $admin->id,
            'endpoint' => 'https://push.example.com/mine',
            'p256dh' => 'pub',
            'auth' => 'auth',
        ]);
        $theirs = PushSubscription::create([
            'user_id' => $other->id,
            'endpoint' => 'https://push.example.com/theirs',
            'p256dh' => 'pub',
            'auth' => 'auth',
        ]);

        $this->actingAs($admin)->postJson('/admin/push/unsubscribe', [
            'endpoint' => 'https://push.example.com/theirs',
        ])->assertOk();

        // The caller does not own the other user's subscription, so it survives.
        $this->assertModelExists($theirs);
        $this->assertModelExists($mine);

        $this->actingAs($admin)->postJson('/admin/push/unsubscribe', [
            'endpoint' => 'https://push.example.com/mine',
        ])->assertOk()->assertJson(['unsubscribed' => true]);

        $this->assertModelMissing($mine);
    }
}
