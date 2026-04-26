<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMediaPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_picker_endpoint_requires_admin_authentication(): void
    {
        $this->get(route('admin.media.picker'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_picker_endpoint_returns_paginated_media_with_metadata(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            Media::create([
                'disk' => 'public',
                'path' => "media/picker-{$i}.jpg",
                'filename' => "picker-{$i}.jpg",
                'mime_type' => 'image/jpeg',
            ]);
        }

        $response = $this->actingAs($user)->getJson(route('admin.media.picker'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'filename', 'alt_text', 'url', 'webp_url']],
                'current_page',
                'last_page',
                'has_more',
                'total',
            ])
            ->assertJsonPath('total', 5)
            ->assertJsonPath('has_more', false);

        $first = $response->json('data.0');
        $this->assertSame('/storage/media/picker-5.jpg', $first['url']);
    }

    public function test_picker_endpoint_filters_by_filename_alt_text_or_id(): void
    {
        $user = User::factory()->create();

        $needle = Media::create([
            'disk' => 'public',
            'path' => 'media/sunrise-ceremony.jpg',
            'filename' => 'sunrise-ceremony.jpg',
            'alt_text' => 'Bride and groom at sunrise',
        ]);

        Media::create([
            'disk' => 'public',
            'path' => 'media/reception-toast.jpg',
            'filename' => 'reception-toast.jpg',
            'alt_text' => 'Toast at reception',
        ]);

        $byFilename = $this->actingAs($user)->getJson(route('admin.media.picker', ['q' => 'sunrise']));
        $byFilename->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $needle->id);

        $byAlt = $this->actingAs($user)->getJson(route('admin.media.picker', ['q' => 'groom']));
        $byAlt->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $needle->id);

        $byId = $this->actingAs($user)->getJson(route('admin.media.picker', ['q' => (string) $needle->id]));
        $byId->assertOk()->assertJsonPath('data.0.id', $needle->id);
    }

    public function test_picker_endpoint_paginates_when_results_exceed_per_page(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 30; $i++) {
            Media::create([
                'disk' => 'public',
                'path' => "media/page-{$i}.jpg",
                'filename' => "page-{$i}.jpg",
            ]);
        }

        $first = $this->actingAs($user)->getJson(route('admin.media.picker', ['per_page' => 12]));

        $first->assertOk()
            ->assertJsonPath('total', 30)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 3)
            ->assertJsonPath('has_more', true);

        $this->assertCount(12, $first->json('data'));

        $third = $this->actingAs($user)->getJson(route('admin.media.picker', ['per_page' => 12, 'page' => 3]));
        $third->assertOk()
            ->assertJsonPath('current_page', 3)
            ->assertJsonPath('has_more', false);
    }

    public function test_admin_wedding_story_form_renders_media_picker_component(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.wedding-stories.create'));

        $response->assertOk()
            ->assertSee('data-media-picker', false)
            ->assertSee(route('admin.media.picker'), false)
            ->assertDontSee('<select name="hero_media_id"', false);
    }
}
