<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardOnYourPlateTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_header_renders_ampersand_without_double_escaping(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Needs reply &amp; this week', false)
            ->assertDontSee('Needs reply &amp;amp; this week', false);
    }

    public function test_future_dated_inquiry_does_not_display_from_now_phrasing(): void
    {
        $user = User::factory()->create();

        Inquiry::factory()->create([
            'primary_name' => 'Future Lead',
            'status' => 'new',
            'first_responded_at' => null,
            'created_at' => now()->addHours(3),
        ]);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Future Lead')
            ->assertSee('received just now')
            ->assertDontSee('from now');
    }
}
