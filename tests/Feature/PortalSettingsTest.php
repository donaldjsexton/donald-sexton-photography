<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_requires_authentication(): void
    {
        $this->get(route('portal.settings.edit'))
            ->assertRedirect(route('portal.login'));
    }

    public function test_authenticated_client_can_view_settings_page(): void
    {
        $client = Client::factory()->withPortalAccess()->create([
            'first_name' => 'Sarah',
            'last_name' => 'Reed',
            'email' => 'sarah@example.com',
        ]);

        $this->actingAs($client, 'client')
            ->get(route('portal.settings.edit'))
            ->assertOk()
            ->assertSee('Profile settings')
            ->assertSee('Communication preferences')
            ->assertSee('sarah@example.com')
            ->assertSee('Sarah');
    }

    public function test_client_can_update_profile(): void
    {
        $client = Client::factory()->withPortalAccess()->create();

        $response = $this->actingAs($client, 'client')
            ->patch(route('portal.settings.update'), [
                'phone' => '555-123-4567',
                'partner_first_name' => 'Alex',
                'partner_last_name' => 'Reed',
                'address_line_1' => '123 Beach Rd',
                'address_line_2' => 'Apt 4',
                'city' => 'Clearwater',
                'state' => 'FL',
                'postal_code' => '33755',
                'country' => 'US',
                'communication_preferences' => ['email', 'sms'],
                'social_media_consent' => '1',
            ]);

        $response->assertRedirect(route('portal.settings.edit'));
        $response->assertSessionHas('status');

        $client->refresh();
        $this->assertSame('555-123-4567', $client->phone);
        $this->assertSame('Alex', $client->partner_first_name);
        $this->assertSame('Reed', $client->partner_last_name);
        $this->assertSame('123 Beach Rd', $client->address_line_1);
        $this->assertSame('Apt 4', $client->address_line_2);
        $this->assertSame('Clearwater', $client->city);
        $this->assertSame('FL', $client->state);
        $this->assertSame('33755', $client->postal_code);
        $this->assertSame(['email', 'sms'], $client->communication_preferences);
        $this->assertTrue($client->social_media_consent);
    }

    public function test_unchecking_social_media_consent_sets_false(): void
    {
        $client = Client::factory()->withPortalAccess()->create([
            'social_media_consent' => true,
        ]);

        $this->actingAs($client, 'client')
            ->patch(route('portal.settings.update'), [
                'social_media_consent' => '0',
            ])
            ->assertRedirect(route('portal.settings.edit'));

        $this->assertFalse($client->refresh()->social_media_consent);
    }

    public function test_invalid_communication_channel_is_rejected(): void
    {
        $client = Client::factory()->withPortalAccess()->create();

        $this->actingAs($client, 'client')
            ->patch(route('portal.settings.update'), [
                'communication_preferences' => ['carrier-pigeon'],
            ])
            ->assertSessionHasErrors('communication_preferences.0');
    }

    public function test_settings_tab_is_visible_in_portal_nav_for_clients(): void
    {
        $client = Client::factory()->withPortalAccess()->create();

        $this->actingAs($client, 'client')
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee(route('portal.settings.edit'), false);
    }
}
