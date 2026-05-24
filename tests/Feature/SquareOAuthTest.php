<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Payments\SquareGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SquareOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.mode' => 'sandbox',
            'payments.gateways.square.enabled' => true,
            'payments.gateways.square.oauth.client_id' => 'app123',
            'payments.gateways.square.oauth.client_secret' => 'secret123',
            'payments.gateways.square.oauth.scopes' => ['MERCHANT_PROFILE_READ', 'PAYMENTS_WRITE'],
        ]);
    }

    public function test_payments_tab_exposes_the_square_connect_button(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.settings.edit', ['tab' => 'payments']))
            ->assertOk()
            ->assertSee('Connect Square')
            ->assertSee(route('admin.settings.square.connect'), false);
    }

    public function test_payments_tab_shows_disconnect_when_connected(): void
    {
        SiteSetting::query()->create([
            'square_merchant_id' => 'M1',
            'square_access_token' => 'tok',
            'square_location_id' => 'LOC1',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.settings.edit', ['tab' => 'payments']))
            ->assertOk()
            ->assertSee('Square is connected')
            ->assertSee(route('admin.settings.square.disconnect'), false);
    }

    public function test_connect_redirects_to_square_with_state(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('admin.settings.square.connect'));

        $response->assertRedirectContains('connect.squareupsandbox.com/oauth2/authorize');
        $response->assertRedirectContains('client_id=app123');
        $response->assertSessionHas('square_oauth_state');
    }

    public function test_callback_stores_tenant_tokens_and_location(): void
    {
        Http::fake([
            'connect.squareupsandbox.com/oauth2/token' => Http::response([
                'access_token' => 'tok-live',
                'refresh_token' => 'refresh-live',
                'expires_at' => now()->addDays(30)->toIso8601String(),
                'merchant_id' => 'MERCHANT1',
            ]),
            'connect.squareupsandbox.com/v2/locations' => Http::response([
                'locations' => [['id' => 'LOC1', 'status' => 'ACTIVE']],
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->withSession(['square_oauth_state' => 'state-abc'])
            ->get(route('admin.settings.square.callback', ['state' => 'state-abc', 'code' => 'auth-code']))
            ->assertRedirect(route('admin.settings.edit', ['tab' => 'integrations']));

        $settings = SiteSetting::current();
        $this->assertTrue($settings->squareIsConnected());
        $this->assertSame('MERCHANT1', $settings->square_merchant_id);
        $this->assertSame('tok-live', $settings->square_access_token);
        $this->assertSame('LOC1', $settings->square_location_id);
    }

    public function test_callback_rejects_state_mismatch(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->withSession(['square_oauth_state' => 'expected'])
            ->get(route('admin.settings.square.callback', ['state' => 'forged', 'code' => 'x']))
            ->assertSessionHas('status_error');

        $this->assertFalse(SiteSetting::current()->squareIsConnected());
        Http::assertNothingSent();
    }

    public function test_disconnect_clears_tokens(): void
    {
        SiteSetting::query()->create([
            'square_merchant_id' => 'M1',
            'square_access_token' => 'tok',
            'square_location_id' => 'LOC1',
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.settings.square.disconnect'))
            ->assertRedirect(route('admin.settings.edit', ['tab' => 'integrations']));

        $this->assertFalse(SiteSetting::current()->squareIsConnected());
    }

    public function test_gateway_prefers_connected_tenant_credentials(): void
    {
        config([
            'payments.gateways.square.sandbox.access_token' => 'config-token',
            'payments.gateways.square.sandbox.location_id' => 'CONFIG-LOC',
        ]);

        // No connection yet: falls back to config.
        $this->assertSame('CONFIG-LOC', app(SquareGateway::class)->locationId());

        SiteSetting::query()->create([
            'square_merchant_id' => 'M1',
            'square_access_token' => 'tenant-token',
            'square_location_id' => 'TENANT-LOC',
        ]);

        $this->assertSame('TENANT-LOC', app(SquareGateway::class)->locationId());
        $this->assertTrue(app(SquareGateway::class)->isConfigured());
    }
}
