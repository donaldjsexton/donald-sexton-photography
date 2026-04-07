<?php

namespace Tests\Feature;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaMaintenanceCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_duplicate_audit_reports_exact_duplicate_groups(): void
    {
        Storage::fake('public');

        $duplicateBytes = $this->makeJpegBytes(1200, 800, 92);
        Storage::disk('public')->put('imports/pictime/duplicates/one.jpg', $duplicateBytes);
        Storage::disk('public')->put('imports/pictime/duplicates/two.jpg', $duplicateBytes);
        Storage::disk('public')->put('imports/pictime/duplicates/unique.jpg', $this->makeJpegBytes(1200, 800, 86));

        Media::create([
            'disk' => 'public',
            'path' => 'imports/pictime/duplicates/one.jpg',
            'filename' => 'one.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 1200,
            'height' => 800,
        ]);

        Media::create([
            'disk' => 'public',
            'path' => 'imports/pictime/duplicates/two.jpg',
            'filename' => 'two.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 1200,
            'height' => 800,
        ]);

        Media::create([
            'disk' => 'public',
            'path' => 'imports/pictime/duplicates/unique.jpg',
            'filename' => 'unique.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 1200,
            'height' => 800,
        ]);

        $reportPath = storage_path('app/private/test-media-duplicates.json');
        File::delete($reportPath);

        try {
            Artisan::call('media:audit-duplicates', [
                '--disk' => 'public',
                '--path-prefix' => 'imports/pictime/duplicates',
                '--report' => $reportPath,
            ]);

            $this->assertFileExists($reportPath);

            $report = json_decode((string) File::get($reportPath), true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame(1, $report['summary']['duplicate_groups']);
            $this->assertSame(2, $report['summary']['duplicate_files']);
            $this->assertCount(1, $report['groups']);
            $this->assertCount(2, $report['groups'][0]['items']);
        } finally {
            File::delete($reportPath);
        }
    }

    public function test_media_optimize_resizes_recompresses_and_generates_webp_variants(): void
    {
        Storage::fake('public');

        $path = 'imports/pictime/optimize/source.jpg';
        $bytes = $this->makeJpegBytes(2200, 1466, 100);
        Storage::disk('public')->put($path, $bytes);

        $media = Media::create([
            'disk' => 'public',
            'path' => $path,
            'filename' => 'source.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 2200,
            'height' => 1466,
        ]);

        $originalBytes = Storage::disk('public')->size($path);

        Artisan::call('media:optimize', [
            '--disk' => 'public',
            '--path-prefix' => 'imports/pictime/optimize',
            '--max-width' => 1600,
            '--jpeg-quality' => 70,
            '--webp-quality' => 70,
            '--min-bytes' => 0,
            '--generate-webp' => true,
        ]);

        $media->refresh();

        $optimizedAbsolutePath = Storage::disk('public')->path($path);
        [$width, $height] = getimagesize($optimizedAbsolutePath);

        $this->assertSame(1600, $width);
        $this->assertSame(1066, $height);
        $this->assertSame(1600, $media->width);
        $this->assertSame(1066, $media->height);
        $this->assertTrue(Storage::disk('public')->exists('imports/pictime/optimize/source.webp'));
        $this->assertLessThan($originalBytes, Storage::disk('public')->size($path));

        $html = Blade::render('<x-editorial.media-frame :media="$media" />', [
            'media' => $media,
        ]);

        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('/storage/imports/pictime/optimize/source.webp', $html);
        $this->assertStringContainsString('/storage/imports/pictime/optimize/source.jpg', $html);
    }

    private function makeJpegBytes(int $width, int $height, int $quality): string
    {
        $image = imagecreatetruecolor($width, $height);

        if (! $image instanceof \GdImage) {
            throw new \RuntimeException('Test image could not be created.');
        }

        try {
            for ($y = 0; $y < $height; $y++) {
                $red = (int) round(255 * ($y / max(1, $height - 1)));
                $blue = 255 - $red;
                $color = imagecolorallocate($image, $red, 96, $blue);
                imageline($image, 0, $y, $width, $y, $color);
            }

            $accent = imagecolorallocate($image, 240, 240, 240);

            for ($x = 0; $x < $width; $x += 160) {
                imageline($image, $x, 0, $width - $x - 1, $height - 1, $accent);
            }

            ob_start();
            imagejpeg($image, null, $quality);

            return (string) ob_get_clean();
        } finally {
            imagedestroy($image);
        }
    }
}
