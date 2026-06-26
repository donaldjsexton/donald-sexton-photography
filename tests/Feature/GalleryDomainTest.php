<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\ShareToken;
use App\Models\Site;
use App\Tenancy\CurrentSite;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GalleryDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_owns_albums_that_hold_ordered_photos(): void
    {
        $gallery = Gallery::factory()->create();
        $album = Album::factory()->for($gallery)->create();

        $first = Photo::factory()->create();
        $second = Photo::factory()->create();

        $album->photos()->attach($second, ['sort_order' => 2]);
        $album->photos()->attach($first, ['sort_order' => 1]);

        $this->assertTrue($gallery->albums->contains($album));
        $this->assertSame(
            [$first->id, $second->id],
            $album->photos()->pluck('photos.id')->all(),
        );
    }

    public function test_uuid_and_slug_are_generated_on_create(): void
    {
        $gallery = Gallery::factory()->create(['title' => 'Smith Wedding', 'slug' => null, 'uuid' => null]);
        $photo = Photo::factory()->create(['uuid' => null]);

        $this->assertNotEmpty($gallery->uuid);
        $this->assertSame('smith-wedding', $gallery->slug);
        $this->assertNotEmpty($photo->uuid);
    }

    public function test_duplicate_titles_produce_distinct_slugs_within_a_site(): void
    {
        $first = Gallery::factory()->create(['title' => 'Smith Wedding', 'slug' => null]);
        $second = Gallery::factory()->create(['title' => 'Smith Wedding', 'slug' => null]);

        $this->assertSame('smith-wedding', $first->slug);
        $this->assertSame('smith-wedding-2', $second->slug);
    }

    public function test_photos_are_deduplicated_per_site_but_not_across_sites(): void
    {
        $default = Site::default();
        $other = Site::factory()->create(['subdomain' => 'studio']);
        $hash = hash('sha256', 'identical-bytes');

        app(CurrentSite::class)->set($default);
        Photo::factory()->create(['sha256' => $hash]);

        // Same hash under a different site is allowed.
        app(CurrentSite::class)->set($other);
        Photo::factory()->create(['sha256' => $hash]);
        $this->assertSame(1, Photo::query()->where('sha256', $hash)->count());

        // A second identical hash within the same site violates the unique key.
        $this->expectException(QueryException::class);
        Photo::factory()->create(['sha256' => $hash]);
    }

    public function test_galleries_are_isolated_by_site(): void
    {
        $default = Site::default();
        $other = Site::factory()->create(['subdomain' => 'studio']);

        app(CurrentSite::class)->set($default);
        $mine = Gallery::factory()->create();

        app(CurrentSite::class)->set($other);
        Gallery::factory()->create();

        app(CurrentSite::class)->set($default);
        $this->assertSame([$mine->id], Gallery::query()->pluck('id')->all());
        $this->assertSame(2, Gallery::withoutSiteScope()->count());
    }

    public function test_share_token_is_polymorphic_and_self_tokenizing(): void
    {
        $gallery = Gallery::factory()->create();
        $token = ShareToken::factory()->create([
            'shareable_type' => Gallery::class,
            'shareable_id' => $gallery->id,
            'token' => null,
        ]);

        $this->assertNotEmpty($token->token);
        $this->assertTrue($gallery->is($token->shareable));
        $this->assertTrue($gallery->shareTokens->contains($token));
        $this->assertFalse($token->isExpired());

        $this->assertTrue(ShareToken::factory()->expired()->create()->isExpired());
    }

    public function test_gallery_password_is_hashed_and_hidden(): void
    {
        $gallery = Gallery::factory()->passwordProtected('letmein')->create();

        $this->assertNotSame('letmein', $gallery->password);
        $this->assertTrue(Hash::check('letmein', $gallery->password));
        $this->assertArrayNotHasKey('password', $gallery->toArray());
    }
}
