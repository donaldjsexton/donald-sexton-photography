<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\ClientFromInquirySync;
use App\Services\GoogleClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ClientFromInquirySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_creates_client_with_split_names_from_inquiry(): void
    {
        $inquiry = Inquiry::factory()->create([
            'primary_name' => 'Sarah Lee',
            'partner_name' => 'James Lee',
            'email' => 'sarah@example.com',
            'phone' => '555-1234',
            'location_city' => 'Tampa',
        ]);

        $client = (new ClientFromInquirySync)->syncFromInquiry($inquiry);

        $this->assertSame('Sarah', $client->first_name);
        $this->assertSame('Lee', $client->last_name);
        $this->assertSame('James', $client->partner_first_name);
        $this->assertSame('Lee', $client->partner_last_name);
        $this->assertSame('sarah@example.com', $client->email);
        $this->assertSame('Tampa', $client->city);
        $this->assertSame($client->id, $inquiry->fresh()->client_id);
        $this->assertNull($client->password);
    }

    public function test_service_returns_existing_client_when_already_linked(): void
    {
        $existing = Client::factory()->create();
        $inquiry = Inquiry::factory()->create(['client_id' => $existing->id]);

        $result = (new ClientFromInquirySync)->syncFromInquiry($inquiry);

        $this->assertSame($existing->id, $result->id);
        $this->assertSame(1, Client::count());
    }

    public function test_service_attaches_repeat_inquiry_to_existing_client_by_email(): void
    {
        $existing = Client::factory()->create(['email' => 'sarah@example.com']);
        $firstInquiry = Inquiry::factory()->create([
            'email' => 'sarah@example.com',
            'client_id' => $existing->id,
        ]);
        $secondInquiry = Inquiry::factory()->create([
            'email' => 'sarah@example.com',
            'client_id' => null,
        ]);

        $result = (new ClientFromInquirySync)->syncFromInquiry($secondInquiry);

        $this->assertSame($existing->id, $result->id);
        $this->assertSame($existing->id, $secondInquiry->fresh()->client_id);
        $this->assertSame(1, Client::count());
        $this->assertCount(2, $existing->fresh()->inquiries);
    }

    public function test_marking_inquiry_booked_creates_linked_client(): void
    {
        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('calendar')->andReturn(null);
        $this->app->instance(GoogleClient::class, $googleClient);

        $admin = User::factory()->create();
        $inquiry = Inquiry::factory()->create([
            'status' => 'new',
            'event_date' => '2027-09-11',
            'primary_name' => 'Sarah Lee',
            'partner_name' => 'James Lee',
            'email' => 'sarah@example.com',
            'phone' => '555-1234',
            'location_city' => 'Clearwater',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.inquiries.update', $inquiry), ['status' => 'booked'])
            ->assertRedirect(route('admin.inquiries.edit', $inquiry));

        $client = $inquiry->fresh()->client;
        $this->assertNotNull($client);
        $this->assertSame('Sarah', $client->first_name);
        $this->assertSame('James', $client->partner_first_name);
        $this->assertSame('sarah@example.com', $client->email);
        $this->assertSame('Clearwater', $client->city);
        $this->assertNull($client->password);
    }

    public function test_marking_inquiry_booked_does_not_duplicate_existing_client(): void
    {
        $googleClient = Mockery::mock(GoogleClient::class);
        $googleClient->shouldReceive('calendar')->andReturn(null);
        $this->app->instance(GoogleClient::class, $googleClient);

        $admin = User::factory()->create();
        $existing = Client::factory()->create();
        $inquiry = Inquiry::factory()->create([
            'status' => 'active',
            'event_date' => '2027-09-11',
            'client_id' => $existing->id,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.inquiries.update', $inquiry), ['status' => 'booked']);

        $this->assertSame(1, Client::count());
        $this->assertSame($existing->id, $inquiry->fresh()->client_id);
    }

    public function test_inquiry_status_other_than_booked_does_not_create_client(): void
    {
        $admin = User::factory()->create();
        $inquiry = Inquiry::factory()->create(['status' => 'new']);

        $this->actingAs($admin)
            ->put(route('admin.inquiries.update', $inquiry), ['status' => 'follow_up']);

        $this->assertSame(0, Client::count());
        $this->assertNull($inquiry->fresh()->client_id);
    }
}
