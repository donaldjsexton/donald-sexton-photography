<?php

namespace Tests\Feature\Portal;

use App\Models\Album;
use App\Models\Client;
use App\Models\Gallery;
use App\Models\Invoice;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
    }

    public function test_index_requires_a_signed_in_client(): void
    {
        $this->get(route('portal.galleries.index'))->assertRedirect(route('portal.login'));
    }

    public function test_a_client_sees_only_their_own_galleries(): void
    {
        $client = $this->client();
        $mine = Gallery::factory()->create(['client_id' => $client->id, 'title' => 'My Wedding']);
        Gallery::factory()->create(['title' => 'Someone Else']);

        $this->actingAs($client, 'client')
            ->get(route('portal.galleries.index'))
            ->assertOk()
            ->assertSee('My Wedding')
            ->assertDontSee('Someone Else');
    }

    public function test_a_client_cannot_open_a_gallery_they_do_not_own(): void
    {
        $client = $this->client();
        $foreign = Gallery::factory()->create();

        $this->actingAs($client, 'client')
            ->get(route('portal.galleries.show', $foreign))
            ->assertNotFound();
    }

    public function test_a_client_can_view_and_download_an_unpaid_but_ungated_gallery(): void
    {
        $client = $this->client();
        $this->outstandingInvoiceFor($client);
        [$gallery, $photo] = $this->galleryWithPhoto($client, requiresPayment: false);

        $this->actingAs($client, 'client')
            ->get(route('portal.galleries.show', $gallery))
            ->assertOk()
            ->assertDontSee('unlock once your balance');

        $this->actingAs($client, 'client')
            ->get(route('portal.galleries.photo.download', ['gallery' => $gallery, 'photo' => $photo->uuid]))
            ->assertOk();

        $this->actingAs($client, 'client')
            ->get(route('portal.galleries.download', $gallery))
            ->assertOk();
    }

    public function test_the_payment_gate_blocks_downloads_but_not_viewing(): void
    {
        $client = $this->client();
        $this->outstandingInvoiceFor($client);
        [$gallery, $photo] = $this->galleryWithPhoto($client, requiresPayment: true);

        // Viewing and the web preview remain available.
        $this->actingAs($client, 'client')
            ->get(route('portal.galleries.show', $gallery))
            ->assertOk()
            ->assertSee('unlock once your balance');

        $this->actingAs($client, 'client')
            ->get(route('portal.galleries.photo', ['gallery' => $gallery, 'photo' => $photo->uuid]))
            ->assertOk();

        // Full-resolution downloads are withheld.
        $this->actingAs($client, 'client')
            ->get(route('portal.galleries.photo.download', ['gallery' => $gallery, 'photo' => $photo->uuid]))
            ->assertForbidden();

        $this->actingAs($client, 'client')
            ->get(route('portal.galleries.download', $gallery))
            ->assertForbidden();
    }

    public function test_a_paid_balance_unlocks_a_gated_gallery(): void
    {
        $client = $this->client();
        Invoice::factory()->paid()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'total_cents' => 50000,
            'amount_paid_cents' => 50000,
        ]);
        [$gallery, $photo] = $this->galleryWithPhoto($client, requiresPayment: true);

        $this->actingAs($client, 'client')
            ->get(route('portal.galleries.photo.download', ['gallery' => $gallery, 'photo' => $photo->uuid]))
            ->assertOk();
    }

    private function client(): Client
    {
        return Client::factory()->withPortalAccess()->create();
    }

    private function outstandingInvoiceFor(Client $client): void
    {
        Invoice::factory()->sent()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'total_cents' => 50000,
            'amount_paid_cents' => 0,
        ]);
    }

    /**
     * @return array{0: Gallery, 1: Photo}
     */
    private function galleryWithPhoto(Client $client, bool $requiresPayment): array
    {
        $gallery = Gallery::factory()->create([
            'client_id' => $client->id,
            'requires_payment' => $requiresPayment,
        ]);
        $album = Album::factory()->for($gallery)->create();
        $photo = Photo::factory()->create();
        Storage::disk('s3')->put($photo->path, 'binary-image-data');
        $album->photos()->attach($photo, ['sort_order' => 1, 'added_at' => now()]);

        return [$gallery, $photo];
    }
}
