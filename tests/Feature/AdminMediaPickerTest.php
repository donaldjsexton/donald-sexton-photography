<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
                'data' => [['id', 'filename', 'alt_text', 'url', 'webp_url', 'width', 'height', 'mime_type', 'kind', 'created_at']],
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

    public function test_picker_endpoint_filters_by_collection_and_mime_type(): void
    {
        $user = User::factory()->create();

        $png = Media::create([
            'disk' => 'public',
            'path' => 'media/flat.png',
            'filename' => 'flat.png',
            'mime_type' => 'image/png',
        ]);

        Media::create([
            'disk' => 'public',
            'path' => 'imports/pictime/album/shot.jpg',
            'filename' => 'shot.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $byType = $this->actingAs($user)->getJson(route('admin.media.picker', ['type' => 'png']));
        $byType->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $png->id);
        $this->assertSame('PNG', $byType->json('data.0.kind'));

        $this->actingAs($user)->getJson(route('admin.media.picker', ['filter' => 'pictime']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.filename', 'shot.jpg');
    }

    public function test_picker_endpoint_sorts_by_name_and_pixel_area(): void
    {
        $user = User::factory()->create();

        Media::create(['disk' => 'public', 'path' => 'media/zebra.jpg', 'filename' => 'zebra.jpg', 'width' => 4000, 'height' => 3000]);
        Media::create(['disk' => 'public', 'path' => 'media/apple.jpg', 'filename' => 'apple.jpg', 'width' => 800, 'height' => 600]);

        $this->actingAs($user)->getJson(route('admin.media.picker', ['sort' => 'name']))
            ->assertOk()
            ->assertJsonPath('data.0.filename', 'apple.jpg');

        $this->actingAs($user)->getJson(route('admin.media.picker', ['sort' => 'largest']))
            ->assertOk()
            ->assertJsonPath('data.0.filename', 'zebra.jpg');

        $this->actingAs($user)->getJson(route('admin.media.picker', ['sort' => 'oldest']))
            ->assertOk()
            ->assertJsonPath('data.0.filename', 'zebra.jpg');
    }

    public function test_picker_endpoint_ignores_unknown_filter_type_and_sort_values(): void
    {
        $user = User::factory()->create();

        Media::create(['disk' => 'public', 'path' => 'media/only.jpg', 'filename' => 'only.jpg', 'mime_type' => 'image/jpeg']);

        $this->actingAs($user)
            ->getJson(route('admin.media.picker', ['filter' => 'nope', 'type' => 'nope', 'sort' => 'nope']))
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_upload_endpoint_requires_admin_authentication(): void
    {
        $this->post(route('admin.media.upload'), [
            'files' => [UploadedFile::fake()->image('one.jpg')],
        ])->assertRedirect(route('admin.login'));
    }

    public function test_upload_endpoint_stores_a_batch_of_photos(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.media.upload'), [
            'files' => [
                UploadedFile::fake()->image('first.jpg', 800, 600),
                UploadedFile::fake()->image('second.png', 640, 480),
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('uploaded', 2)
            ->assertJsonPath('failed', [])
            ->assertJsonCount(2, 'data');

        $this->assertSame(2, Media::query()->count());

        $first = Media::query()->where('filename', 'first.jpg')->firstOrFail();
        $this->assertSame(800, $first->width);
        $this->assertSame(600, $first->height);
        Storage::disk('public')->assertExists($first->path);
    }

    public function test_upload_endpoint_reports_rejected_files_without_dropping_the_batch(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('admin.media.upload'), [
            'files' => [
                UploadedFile::fake()->image('keeper.jpg', 400, 300),
                UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf'),
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('uploaded', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'failed')
            ->assertJsonPath('failed.0.filename', 'notes.pdf');

        $this->assertSame(1, Media::query()->count());
        $this->assertDatabaseHas('media', ['filename' => 'keeper.jpg']);
    }

    public function test_upload_endpoint_rejects_a_batch_with_no_usable_files(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('admin.media.upload'), [
            'files' => [UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf')],
        ])->assertStatus(422)->assertJsonPath('uploaded', 0);

        $this->assertSame(0, Media::query()->count());
    }

    public function test_admin_wedding_story_form_renders_media_picker_component(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.wedding-stories.create'));

        $response->assertOk()
            ->assertSee('data-media-picker', false)
            ->assertSee(route('admin.media.picker'), false)
            ->assertSee(route('admin.media.upload'), false)
            ->assertDontSee('<select name="hero_media_id"', false);
    }
}
