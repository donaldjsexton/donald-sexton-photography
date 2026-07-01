<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Photo;
use App\Services\Galleries\PhotoIngestionService;
use App\Services\Galleries\PhotoVariant;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToCreateDirectory;
use Mockery;
use Tests\TestCase;

class PhotoIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
    }

    public function test_it_ingests_an_image_with_dimensions_and_variants(): void
    {
        $album = Album::factory()->create();
        $path = $this->makeJpeg(1200, 800);

        $result = (new PhotoIngestionService)->ingest($path, $album, 'beach.jpg');

        $this->assertTrue($result->isCreated());

        $photo = $result->photo;
        $this->assertSame(1200, $photo->width);
        $this->assertSame(800, $photo->height);
        $this->assertSame('beach.jpg', $photo->original_name);
        $this->assertSame('image/jpeg', $photo->mime_type);
        $this->assertSame(64, strlen($photo->sha256));
        $this->assertSame($album->site_id, $photo->site_id);

        $disk = Storage::disk('s3');
        $disk->assertExists($photo->path);
        $service = new PhotoIngestionService;
        $disk->assertExists($service->variantPath($photo->path, PhotoVariant::Thumb));
        $disk->assertExists($service->variantPath($photo->path, PhotoVariant::Web));

        $this->assertTrue($album->photos()->whereKey($photo->getKey())->exists());
    }

    public function test_identical_uploads_are_deduplicated_and_idempotent(): void
    {
        $album = Album::factory()->create();
        $path = $this->makeJpeg(800, 600, fill: [10, 20, 30]);

        $service = new PhotoIngestionService;
        $first = $service->ingest($path, $album, 'a.jpg');
        $second = $service->ingest($path, $album, 'a-again.jpg');

        $this->assertTrue($first->isCreated());
        $this->assertTrue($second->isDuplicate());
        $this->assertSame($first->photo->id, $second->photo->id);
        $this->assertSame(1, Photo::query()->count());
        $this->assertSame(1, $album->photos()->count());
    }

    public function test_distinct_images_each_persist_and_increment_order(): void
    {
        $album = Album::factory()->create();
        $service = new PhotoIngestionService;

        $service->ingest($this->makeJpeg(400, 400, fill: [1, 2, 3]), $album);
        $service->ingest($this->makeJpeg(400, 400, fill: [250, 240, 230]), $album);

        $this->assertSame(2, $album->photos()->count());
        $this->assertSame([1, 2], $album->photos()->pluck('album_photo.sort_order')->all());
    }

    public function test_a_corrupt_file_fails_fast_without_persisting(): void
    {
        $album = Album::factory()->create();
        $path = tempnam(sys_get_temp_dir(), 'corrupt').'.jpg';
        file_put_contents($path, 'this is not an image');

        $result = (new PhotoIngestionService)->ingest($path, $album);

        $this->assertTrue($result->isFailed());
        $this->assertSame('unreadable_image', $result->reason);
        $this->assertSame(0, Photo::query()->count());
        $this->assertEmpty(Storage::disk('s3')->allFiles());

        @unlink($path);
    }

    public function test_an_unsupported_format_is_rejected(): void
    {
        $album = Album::factory()->create();
        $path = tempnam(sys_get_temp_dir(), 'gif').'.gif';
        $image = imagecreatetruecolor(20, 20);
        imagegif($image, $path);
        imagedestroy($image);

        $result = (new PhotoIngestionService)->ingest($path, $album);

        $this->assertTrue($result->isFailed());
        $this->assertSame('unsupported_mime', $result->reason);
        $this->assertSame(0, Photo::query()->count());

        @unlink($path);
    }

    public function test_a_rejected_disk_write_fails_the_ingest_without_persisting(): void
    {
        $album = Album::factory()->create();
        $path = $this->makeJpeg(400, 300);

        // The s3/R2 disk is configured with throw=false, so a failed write
        // (bad credentials, missing bucket) surfaces as a false return value.
        Storage::shouldReceive('disk')->with('s3')->andReturn($disk = Mockery::mock(Filesystem::class));
        $disk->shouldReceive('writeStream')->once()->andReturn(false);

        $result = (new PhotoIngestionService)->ingest($path, $album, 'beach.jpg');

        $this->assertTrue($result->isFailed());
        $this->assertSame('storage_write_failed', $result->reason);
        $this->assertSame(0, Photo::query()->count());
        $this->assertSame(0, $album->photos()->count());

        @unlink($path);
    }

    public function test_an_unwritable_disk_fails_the_ingest_without_persisting(): void
    {
        $album = Album::factory()->create();
        $path = $this->makeJpeg(400, 300);

        // Local drivers throw on directory-creation failure even with throw=false.
        Storage::shouldReceive('disk')->with('s3')->andReturn($disk = Mockery::mock(Filesystem::class));
        $disk->shouldReceive('writeStream')->once()->andThrow(
            UnableToCreateDirectory::atLocation('galleries/1/1', 'Permission denied'),
        );

        $result = (new PhotoIngestionService)->ingest($path, $album, 'beach.jpg');

        $this->assertTrue($result->isFailed());
        $this->assertSame('storage_write_failed', $result->reason);
        $this->assertSame(0, Photo::query()->count());

        @unlink($path);
    }

    /**
     * Write a solid-colour JPEG to a temp path and return it.
     *
     * @param  array{int,int,int}  $fill
     */
    private function makeJpeg(int $width, int $height, array $fill = [120, 140, 160]): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gallery-src').'.jpg';
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, $fill[0], $fill[1], $fill[2]);
        imagefilledrectangle($image, 0, 0, $width, $height, $color);
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        return $path;
    }
}
