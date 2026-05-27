<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Support\LocationPageDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationPageGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_generate_a_draft_location_page_from_a_city_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.pages.generate-location'), [
            'city' => 'Tampa',
            'state' => 'FL',
            'region' => 'Tampa Bay',
        ]);

        $page = Page::query()->where('slug', 'tampa')->firstOrFail();

        $response->assertRedirect(route('admin.pages.edit', $page));

        $this->assertSame('Tampa Wedding Photographer', $page->title);
        $this->assertSame('location', $page->template);
        $this->assertSame('draft', $page->status);
        $this->assertSame('Tampa Wedding Photographer | Donald Sexton', $page->seo_title);
        $this->assertNotNull($page->seo_description);
        $this->assertStringContainsString('Tampa, FL', $page->excerpt);
        $this->assertStringContainsString('Tampa Bay', $page->body);
    }

    public function test_generator_appends_a_numeric_suffix_when_slug_is_taken(): void
    {
        $user = User::factory()->create();

        Page::create([
            'title' => 'Tampa',
            'slug' => 'tampa',
            'template' => 'custom',
            'status' => 'published',
        ]);

        $this->actingAs($user)->post(route('admin.pages.generate-location'), [
            'city' => 'Tampa',
        ])->assertRedirect();

        $this->assertTrue(Page::query()->where('slug', 'tampa-2')->exists());
    }

    public function test_generator_requires_a_city(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('admin.pages.index'))
            ->post(route('admin.pages.generate-location'), ['city' => ''])
            ->assertRedirect(route('admin.pages.index'))
            ->assertSessionHasErrors('city');

        $this->assertSame(0, Page::query()->count());
    }

    public function test_generator_route_requires_authentication(): void
    {
        $this->post(route('admin.pages.generate-location'), ['city' => 'Tampa'])
            ->assertRedirect(route('admin.login'));
    }

    public function test_draft_omits_region_phrasing_when_region_is_blank(): void
    {
        $draft = LocationPageDraft::build('Clearwater', 'FL');

        $this->assertStringNotContainsString('broader  area', $draft->body);
        $this->assertStringContainsString('along the coast', $draft->body);
    }

    public function test_draft_seo_description_is_within_160_chars(): void
    {
        $draft = LocationPageDraft::build('St. Petersburg', 'FL');

        $this->assertLessThanOrEqual(160, mb_strlen($draft->seoDescription));
    }
}
