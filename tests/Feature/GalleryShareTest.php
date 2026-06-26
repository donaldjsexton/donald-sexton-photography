<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\ShareToken;
use App\Models\Site;
use App\Tenancy\CurrentSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryShareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
    }

    public function test_a_shared_gallery_renders_its_photos(): void
    {
        ['token' => $token, 'photos' => $photos] = $this->makeSharedGallery(2);

        $response = $this->get(route('galleries.share.show', $token->token));

        $response->assertOk();
        $response->assertSee($token->shareable->title);
        foreach ($photos as $photo) {
            $response->assertSee($photo->uuid, escape: false);
        }
    }

    public function test_an_expired_token_is_not_found(): void
    {
        $gallery = Gallery::factory()->create();
        $token = ShareToken::factory()->expired()->create([
            'shareable_type' => Gallery::class,
            'shareable_id' => $gallery->id,
        ]);

        $this->get(route('galleries.share.show', $token->token))->assertNotFound();
    }

    public function test_a_token_from_another_site_is_not_resolvable(): void
    {
        $other = Site::factory()->create(['subdomain' => 'studio']);
        app(CurrentSite::class)->set($other);
        $gallery = Gallery::factory()->create();
        $token = ShareToken::factory()->create([
            'shareable_type' => Gallery::class,
            'shareable_id' => $gallery->id,
        ]);
        app(CurrentSite::class)->set(Site::default());

        $this->get(route('galleries.share.show', $token->token))->assertNotFound();
    }

    public function test_a_password_protected_gallery_prompts_then_unlocks(): void
    {
        ['token' => $token, 'photos' => $photos] = $this->makeSharedGallery(1, password: 'secret');
        $photo = $photos->first();

        $this->get(route('galleries.share.show', $token->token))
            ->assertOk()
            ->assertSee('This gallery is protected')
            ->assertDontSee($photo->uuid, escape: false);

        $this->post(route('galleries.share.unlock', $token->token), ['password' => 'nope'])
            ->assertOk()
            ->assertSee('not correct');

        $this->post(route('galleries.share.unlock', $token->token), ['password' => 'secret'])
            ->assertRedirect(route('galleries.share.show', $token->token));

        $this->get(route('galleries.share.show', $token->token))
            ->assertOk()
            ->assertSee($photo->uuid, escape: false);
    }

    public function test_a_locked_gallery_blocks_asset_access(): void
    {
        ['token' => $token, 'photos' => $photos] = $this->makeSharedGallery(1, password: 'secret');

        $this->get(route('galleries.share.photo', ['token' => $token->token, 'photo' => $photos->first()->uuid]))
            ->assertForbidden();
    }

    public function test_a_photo_streams_and_foreign_photos_are_rejected(): void
    {
        ['token' => $token, 'photos' => $photos] = $this->makeSharedGallery(1);
        $foreign = Photo::factory()->create();

        $this->get(route('galleries.share.photo', ['token' => $token->token, 'photo' => $photos->first()->uuid]))
            ->assertOk();

        $this->get(route('galleries.share.photo', ['token' => $token->token, 'photo' => $foreign->uuid]))
            ->assertNotFound();
    }

    public function test_download_all_returns_a_zip(): void
    {
        ['token' => $token] = $this->makeSharedGallery(2);

        $response = $this->get(route('galleries.share.download', $token->token));

        $response->assertOk();
        $response->assertDownload();
        $this->assertStringContainsString('.zip', (string) $response->headers->get('content-disposition'));
    }

    /**
     * Build a gallery + album with ingested-style photos and a share token.
     *
     * @return array{token: ShareToken, gallery: Gallery, album: Album, photos: Collection<int, Photo>}
     */
    private function makeSharedGallery(int $photoCount, ?string $password = null): array
    {
        $gallery = Gallery::factory()->create();
        $album = Album::factory()->for($gallery)->create();

        $photos = collect(range(1, $photoCount))->map(function (int $index) use ($album): Photo {
            $photo = Photo::factory()->create();
            Storage::disk('s3')->put($photo->path, 'binary-image-data-'.$index);
            $album->photos()->attach($photo, ['sort_order' => $index, 'added_at' => now()]);

            return $photo;
        });

        $token = ShareToken::factory()
            ->when($password !== null, fn ($factory) => $factory->passwordProtected($password))
            ->create([
                'shareable_type' => Gallery::class,
                'shareable_id' => $gallery->id,
            ]);

        return ['token' => $token, 'gallery' => $gallery, 'album' => $album, 'photos' => $photos];
    }
}
