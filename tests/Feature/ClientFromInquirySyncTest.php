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
        $this->assertSame($inquiry->id, $client->inquiry_id);
        $this->assertNull($client->password);
    }

    public function test_service_returns_existing_client_when_already_linked(): void
    {
        $inquiry = Inquiry::factory()->create();
        $existing = Client::factory()->create(['inquiry_id' => $inquiry->id]);

        $result = (new ClientFromInquirySync)->syncFromInquiry($inquiry);

        $this->assertSame($existing->id, $result->id);
        $this->assertSame(1, Client::where('inquiry_id', $inquiry->id)->count());
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

        $client = Client::where('inquiry_id', $inquiry->id)->first();
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
        $inquiry = Inquiry::factory()->create([
            'status' => 'active',
            'event_date' => '2027-09-11',
        ]);
        Client::factory()->create(['inquiry_id' => $inquiry->id]);

        $this->actingAs($admin)
            ->put(route('admin.inquiries.update', $inquiry), ['status' => 'booked']);

        $this->assertSame(1, Client::where('inquiry_id', $inquiry->id)->count());
    }

    public function test_inquiry_status_other_than_booked_does_not_create_client(): void
    {
        $admin = User::factory()->create();
        $inquiry = Inquiry::factory()->create(['status' => 'new']);

        $this->actingAs($admin)
            ->put(route('admin.inquiries.update', $inquiry), ['status' => 'follow_up']);

        $this->assertSame(0, Client::where('inquiry_id', $inquiry->id)->count());
    }
}
