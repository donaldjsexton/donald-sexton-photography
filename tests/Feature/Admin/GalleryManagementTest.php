<?php

namespace Tests\Feature\Admin;

use App\Models\Album;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\ShareToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
    }

    public function test_index_requires_authentication(): void
    {
        $this->get(route('admin.galleries.index'))->assertRedirect('/admin/login');
    }

    public function test_admin_can_create_a_gallery(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.galleries.store'), [
            'title' => 'Smith Wedding',
            'visibility' => 'private',
            'password' => 'secret',
        ]);

        $gallery = Gallery::query()->firstOrFail();
        $response->assertRedirect(route('admin.galleries.edit', $gallery));
        $this->assertSame('Smith Wedding', $gallery->title);
        $this->assertSame('smith-wedding', $gallery->slug);
        $this->assertTrue(Hash::check('secret', $gallery->password));
    }

    public function test_admin_can_update_and_clear_the_password(): void
    {
        $gallery = Gallery::factory()->passwordProtected('old')->create();

        $this->actingAs($this->admin())->put(route('admin.galleries.update', $gallery), [
            'title' => 'Renamed',
            'visibility' => 'public',
            'remove_password' => '1',
        ])->assertRedirect(route('admin.galleries.edit', $gallery));

        $gallery->refresh();
        $this->assertSame('Renamed', $gallery->title);
        $this->assertSame('public', $gallery->visibility);
        $this->assertNull($gallery->password);
    }

    public function test_admin_can_add_rename_and_delete_albums(): void
    {
        $gallery = Gallery::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.galleries.albums.store', $gallery), [
            'name' => 'Ceremony',
            'visibility' => 'private',
        ])->assertRedirect();

        $album = $gallery->albums()->firstOrFail();
        $this->assertSame('Ceremony', $album->name);

        $this->actingAs($admin)->put(route('admin.galleries.albums.update', [$gallery, $album]), [
            'name' => 'Reception',
            'visibility' => 'public',
        ])->assertRedirect();
        $this->assertSame('Reception', $album->refresh()->name);

        $this->actingAs($admin)->delete(route('admin.galleries.albums.destroy', [$gallery, $album]))->assertRedirect();
        $this->assertDatabaseMissing('albums', ['id' => $album->id]);
    }

    public function test_admin_can_upload_photos_into_an_album(): void
    {
        $gallery = Gallery::factory()->create();
        $album = Album::factory()->for($gallery)->create();

        $this->actingAs($this->admin())->post(route('admin.galleries.albums.photos.store', [$gallery, $album]), [
            'photos' => [
                UploadedFile::fake()->image('one.jpg', 800, 600),
                UploadedFile::fake()->image('two.jpg', 640, 480),
            ],
        ])->assertRedirect();

        $this->assertSame(2, $album->photos()->count());

        $photo = $album->photos()->first();
        Storage::disk('s3')->assertExists($photo->path);
        $this->assertSame($gallery->site_id, $photo->site_id);
    }

    public function test_admin_can_set_a_cover_and_remove_a_photo(): void
    {
        $gallery = Gallery::factory()->create();
        $album = Album::factory()->for($gallery)->create();
        $photo = Photo::factory()->create();
        Storage::disk('s3')->put($photo->path, 'bytes');
        $album->photos()->attach($photo, ['sort_order' => 1, 'added_at' => now()]);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.galleries.cover', [$gallery, $photo]))->assertRedirect();
        $this->assertSame($photo->id, $gallery->refresh()->cover_photo_id);

        $this->actingAs($admin)->delete(route('admin.galleries.albums.photos.destroy', [$gallery, $album, $photo]))->assertRedirect();
        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
        Storage::disk('s3')->assertMissing($photo->path);
    }

    public function test_admin_can_create_and_revoke_share_links(): void
    {
        $gallery = Gallery::factory()->create();
        $album = Album::factory()->for($gallery)->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.galleries.shares.store', $gallery), [
            'album_id' => $album->id,
            'password' => 'view',
        ])->assertRedirect();

        $token = ShareToken::query()->firstOrFail();
        $this->assertSame(Album::class, $token->shareable_type);
        $this->assertSame($album->id, (int) $token->shareable_id);
        $this->assertTrue(Hash::check('view', $token->password));

        $this->actingAs($admin)->delete(route('admin.galleries.shares.destroy', [$gallery, $token]))->assertRedirect();
        $this->assertDatabaseMissing('share_tokens', ['id' => $token->id]);
    }

    public function test_album_routes_reject_a_mismatched_gallery(): void
    {
        $galleryA = Gallery::factory()->create();
        $galleryB = Gallery::factory()->create();
        $album = Album::factory()->for($galleryB)->create();

        $this->actingAs($this->admin())
            ->put(route('admin.galleries.albums.update', [$galleryA, $album]), [
                'name' => 'Hijack',
                'visibility' => 'private',
            ])
            ->assertNotFound();
    }

    public function test_deleting_a_gallery_removes_its_photos(): void
    {
        $gallery = Gallery::factory()->create();
        $album = Album::factory()->for($gallery)->create();
        $photo = Photo::factory()->create();
        Storage::disk('s3')->put($photo->path, 'bytes');
        $album->photos()->attach($photo, ['sort_order' => 1, 'added_at' => now()]);

        $this->actingAs($this->admin())
            ->delete(route('admin.galleries.destroy', $gallery))
            ->assertRedirect(route('admin.galleries.index'));

        $this->assertDatabaseMissing('galleries', ['id' => $gallery->id]);
        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
        Storage::disk('s3')->assertMissing($photo->path);
    }

    private function admin(): User
    {
        return User::factory()->create();
    }
}
