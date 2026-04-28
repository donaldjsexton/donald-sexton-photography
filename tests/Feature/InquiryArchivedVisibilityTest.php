<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InquiryArchivedVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_inquiries_index_hides_archived_from_default_view(): void
    {
        $user = User::factory()->create();

        $active = Inquiry::factory()->create([
            'primary_name' => 'Active Lead',
            'status' => 'new',
        ]);

        $archived = Inquiry::factory()->archived()->create([
            'primary_name' => 'Archived Lead',
        ]);

        $response = $this->actingAs($user)->get(route('admin.inquiries.index'));

        $response->assertOk()
            ->assertSee($active->primary_name)
            ->assertDontSee($archived->primary_name);
    }

    public function test_admin_inquiries_index_shows_archived_when_status_filter_is_selected(): void
    {
        $user = User::factory()->create();

        $active = Inquiry::factory()->create([
            'primary_name' => 'Active Lead',
            'status' => 'new',
        ]);

        $archived = Inquiry::factory()->archived()->create([
            'primary_name' => 'Archived Lead',
        ]);

        $response = $this->actingAs($user)->get(route('admin.inquiries.index', ['status' => 'archived']));

        $response->assertOk()
            ->assertSee($archived->primary_name)
            ->assertDontSee($active->primary_name);
    }

    public function test_admin_inquiries_all_count_excludes_archived(): void
    {
        $user = User::factory()->create();

        Inquiry::factory()->count(2)->create(['status' => 'new']);
        Inquiry::factory()->archived()->count(3)->create();

        $response = $this->actingAs($user)->get(route('admin.inquiries.index'));

        $response->assertOk()
            ->assertSee('All (2)')
            ->assertSee('Archived (3)');
    }

    public function test_admin_dashboard_recent_inquiries_excludes_archived(): void
    {
        $user = User::factory()->create();

        $visible = Inquiry::factory()->create([
            'primary_name' => 'Visible Lead',
            'status' => 'new',
        ]);

        $archived = Inquiry::factory()->archived()->create([
            'primary_name' => 'Archived Lead',
        ]);

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee($visible->primary_name)
            ->assertDontSee($archived->primary_name);
    }
}
