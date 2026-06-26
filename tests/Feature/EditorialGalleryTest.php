<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Gallery;
use App\Models\JournalPost;
use App\Models\Photo;
use App\Models\User;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EditorialGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
    }

    public function test_a_wedding_story_renders_its_attached_client_gallery(): void
    {
        [$gallery, $photo] = $this->galleryWithPhoto();
        $story = WeddingStory::create([
            'title' => 'Riverside Wedding',
            'slug' => 'riverside-wedding',
            'status' => 'published',
            'gallery_id' => $gallery->id,
            'published_at' => now(),
        ]);

        $this->get(route('weddings.show', $story->slug))
            ->assertOk()
            ->assertSee(route('galleries.embed.photo', ['gallery' => $gallery, 'photo' => $photo->uuid]), escape: false);
    }

    public function test_a_journal_post_renders_its_attached_client_gallery(): void
    {
        [$gallery, $photo] = $this->galleryWithPhoto();
        $post = JournalPost::create([
            'title' => 'A Real Wedding',
            'slug' => 'a-real-wedding',
            'status' => 'published',
            'gallery_id' => $gallery->id,
            'published_at' => now(),
        ]);

        $this->get(route('journal.show', $post->slug))
            ->assertOk()
            ->assertSee(route('galleries.embed.photo', ['gallery' => $gallery, 'photo' => $photo->uuid]), escape: false);
    }

    public function test_embed_photo_streams_when_referenced_by_a_published_story(): void
    {
        [$gallery, $photo] = $this->galleryWithPhoto();
        WeddingStory::create([
            'title' => 'Published Story',
            'slug' => 'published-story',
            'status' => 'published',
            'gallery_id' => $gallery->id,
            'published_at' => now(),
        ]);

        $this->get(route('galleries.embed.photo', ['gallery' => $gallery, 'photo' => $photo->uuid]))
            ->assertOk();
    }

    public function test_embed_photo_is_hidden_for_a_draft_story_gallery(): void
    {
        [$gallery, $photo] = $this->galleryWithPhoto();
        WeddingStory::create([
            'title' => 'Draft Story',
            'slug' => 'draft-story',
            'status' => 'draft',
            'gallery_id' => $gallery->id,
        ]);

        $this->get(route('galleries.embed.photo', ['gallery' => $gallery, 'photo' => $photo->uuid]))
            ->assertNotFound();
    }

    public function test_embed_photo_is_hidden_for_an_unreferenced_private_gallery(): void
    {
        [$gallery, $photo] = $this->galleryWithPhoto();

        $this->get(route('galleries.embed.photo', ['gallery' => $gallery, 'photo' => $photo->uuid]))
            ->assertNotFound();
    }

    public function test_a_public_gallery_is_embeddable_without_editorial_reference(): void
    {
        [$gallery, $photo] = $this->galleryWithPhoto(public: true);

        $this->get(route('galleries.embed.photo', ['gallery' => $gallery, 'photo' => $photo->uuid]))
            ->assertOk();
    }

    public function test_the_admin_story_form_offers_a_gallery_picker(): void
    {
        $gallery = Gallery::factory()->create(['title' => 'Pickable Gallery']);
        $story = WeddingStory::create([
            'title' => 'Editable',
            'slug' => 'editable',
            'status' => 'draft',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.wedding-stories.edit', $story))
            ->assertOk()
            ->assertSee('Client gallery')
            ->assertSee('Pickable Gallery');
    }

    /**
     * @return array{0: Gallery, 1: Photo}
     */
    private function galleryWithPhoto(bool $public = false): array
    {
        $gallery = Gallery::factory()->when($public, fn ($factory) => $factory->public())->create();
        $album = Album::factory()->for($gallery)->create();
        $photo = Photo::factory()->create();
        Storage::disk('s3')->put($photo->path, 'binary-image-data');
        $album->photos()->attach($photo, ['sort_order' => 1, 'added_at' => now()]);

        return [$gallery, $photo];
    }
}
