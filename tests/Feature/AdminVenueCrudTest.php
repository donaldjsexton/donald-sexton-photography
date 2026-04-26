<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminVenueCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_venues_index_requires_auth(): void
    {
        $this->get('/admin/venues')->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_venue(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/admin/venues', [
            'name' => 'Hudson Valley Estate',
            'city' => 'Rhinebeck',
            'state' => 'NY',
            'region' => 'Hudson Valley',
            'website_url' => 'https://example.com',
            'referral_emails' => 'planner@example.com, events@example.com',
            'referral_contact_name' => 'Jane Planner',
            'is_featured' => '1',
        ]);

        $venue = Venue::query()->where('name', 'Hudson Valley Estate')->firstOrFail();

        $response->assertRedirect(route('admin.venues.edit', $venue));

        $this->assertSame('hudson-valley-estate', $venue->slug);
        $this->assertTrue($venue->is_featured);
        $this->assertSame(['planner@example.com', 'events@example.com'], $venue->referral_emails);
        $this->assertSame('Jane Planner', $venue->referral_contact_name);
    }

    public function test_admin_can_update_venue_and_strip_invalid_referral_emails(): void
    {
        $user = User::factory()->create();
        $venue = Venue::query()->create([
            'name' => 'Old Barn',
            'slug' => 'old-barn',
        ]);

        $this->actingAs($user)->put("/admin/venues/{$venue->id}", [
            'name' => 'Restored Barn',
            'slug' => 'old-barn',
            'referral_emails' => 'good@example.com; not-an-email; another@example.com',
        ])->assertRedirect(route('admin.venues.edit', $venue));

        $venue->refresh();

        $this->assertSame('Restored Barn', $venue->name);
        $this->assertSame(['good@example.com', 'another@example.com'], $venue->referral_emails);
    }

    public function test_admin_can_delete_venue(): void
    {
        $user = User::factory()->create();
        $venue = Venue::query()->create([
            'name' => 'Removed Venue',
            'slug' => 'removed-venue',
        ]);

        $this->actingAs($user)->delete("/admin/venues/{$venue->id}")
            ->assertRedirect(route('admin.venues.index'));

        $this->assertDatabaseMissing('venues', ['id' => $venue->id]);
    }
}
