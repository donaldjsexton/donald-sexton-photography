<?php

namespace Tests\Feature;

use App\Models\JournalPost;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The web server serves files from the public disk as its own user, so
 * anything written there has to stay world-readable. A file left at 0600
 * is invisible to it and every URL pointing at it 404s, which surfaces as
 * a broken image rather than as an error anywhere in the application.
 *
 * These tests deliberately avoid `Storage::fake()`: the fake disk drops the
 * configured `visibility` and writes everything at 0600, which would hide
 * exactly the regression under test. Instead the real `public` disk config
 * is kept and only its root is redirected at a scratch directory.
 */
class MediaFilePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private string $diskRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->diskRoot = storage_path('framework/testing/media-permissions');
        File::deleteDirectory($this->diskRoot);
        File::ensureDirectoryExists($this->diskRoot);

        config(['filesystems.disks.public.root' => $this->diskRoot]);
        Storage::forgetDisk('public');
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('public');
        File::deleteDirectory($this->diskRoot);

        parent::tearDown();
    }

    public function test_uploaded_media_stays_readable_by_the_web_server(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.media.store'), [
                'file' => UploadedFile::fake()->image('ceremony.jpg', 2400, 1600),
            ])
            ->assertRedirect();

        $media = Media::query()->latest('id')->first();

        $this->assertNotNull($media);

        $this->assertFileIsWorldReadable(
            Storage::disk('public')->path($media->path),
            'The stored original must stay readable after the optimizer replaces it.'
        );

        foreach (['webp', 'avif'] as $format) {
            $siblingPath = preg_replace('/\.[^.]+$/', '.'.$format, $media->path);

            if (Storage::disk('public')->exists($siblingPath)) {
                $this->assertFileIsWorldReadable(Storage::disk('public')->path($siblingPath));
            }
        }
    }

    public function test_media_optimize_leaves_the_replaced_original_readable(): void
    {
        $path = 'imports/pictime/permissions/source.jpg';
        Storage::disk('public')->put($path, $this->makeJpegBytes(2200, 1466));

        $absolutePath = Storage::disk('public')->path($path);

        // Stand in for the mode a `tempnam()` staging file leaves behind
        // when it is renamed over the original.
        chmod($absolutePath, 0600);

        Media::create([
            'disk' => 'public',
            'path' => $path,
            'filename' => 'source.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 2200,
            'height' => 1466,
        ]);

        Artisan::call('media:optimize', [
            '--disk' => 'public',
            '--path-prefix' => 'imports/pictime/permissions',
            '--max-width' => 1600,
            '--min-bytes' => 0,
            '--generate-webp' => true,
        ]);

        $this->assertFileIsWorldReadable($absolutePath);
    }

    public function test_media_optimize_does_not_touch_modes_during_a_dry_run(): void
    {
        $path = 'imports/pictime/permissions/dry-run.jpg';
        Storage::disk('public')->put($path, $this->makeJpegBytes(2200, 1466));

        $absolutePath = Storage::disk('public')->path($path);
        chmod($absolutePath, 0600);

        Media::create([
            'disk' => 'public',
            'path' => $path,
            'filename' => 'dry-run.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 2200,
            'height' => 1466,
        ]);

        Artisan::call('media:optimize', [
            '--disk' => 'public',
            '--path-prefix' => 'imports/pictime/permissions',
            '--max-width' => 1600,
            '--min-bytes' => 0,
            '--dry-run' => true,
        ]);

        $this->assertSame(0600, $this->modeOf($absolutePath));
    }

    public function test_repaired_legacy_wordpress_uploads_stay_readable_by_the_web_server(): void
    {
        $legacyRoot = storage_path('app/private/test-legacy-permissions');
        $relativePath = 'tests/legacy-permissions.jpg';
        $sourcePath = $legacyRoot.'/'.$relativePath;
        $publicPath = public_path('wp-content/uploads/'.$relativePath);

        File::ensureDirectoryExists(dirname($sourcePath));
        File::put($sourcePath, $this->makeJpegBytes(64, 64));
        File::delete($publicPath);

        $post = JournalPost::query()->create([
            'title' => 'Legacy Permissions',
            'slug' => 'legacy-permissions',
            'status' => 'published',
            'post_type' => 'advice',
            'body' => '<p><img src="https://donaldsextonphotography.com/wp-content/uploads/'.$relativePath.'" alt=""></p>',
            'original_wp_post_id' => 7101,
        ]);

        $previous = getenv('LEGACY_WORDPRESS_UPLOADS_PATH') ?: null;
        putenv('LEGACY_WORDPRESS_UPLOADS_PATH='.$legacyRoot);
        $_ENV['LEGACY_WORDPRESS_UPLOADS_PATH'] = $legacyRoot;
        $_SERVER['LEGACY_WORDPRESS_UPLOADS_PATH'] = $legacyRoot;

        try {
            Artisan::call('legacy:repair-media', ['--slug' => [$post->slug]]);

            $this->assertFileIsWorldReadable(
                $publicPath,
                'Legacy uploads placed by an atomic rename must not inherit the staging file mode.'
            );
        } finally {
            if ($previous !== null) {
                putenv('LEGACY_WORDPRESS_UPLOADS_PATH='.$previous);
                $_ENV['LEGACY_WORDPRESS_UPLOADS_PATH'] = $previous;
                $_SERVER['LEGACY_WORDPRESS_UPLOADS_PATH'] = $previous;
            } else {
                putenv('LEGACY_WORDPRESS_UPLOADS_PATH');
                unset($_ENV['LEGACY_WORDPRESS_UPLOADS_PATH'], $_SERVER['LEGACY_WORDPRESS_UPLOADS_PATH']);
            }

            File::delete($publicPath);
            File::deleteDirectory($legacyRoot);
        }
    }

    public function test_fix_permissions_command_repairs_unreadable_files_and_directories(): void
    {
        Storage::disk('public')->put('media/2026/08/unreadable.jpg', $this->makeJpegBytes(64, 64));
        Storage::disk('public')->put('media/2026/08/already-fine.jpg', $this->makeJpegBytes(64, 64));

        $unreadable = Storage::disk('public')->path('media/2026/08/unreadable.jpg');
        $readable = Storage::disk('public')->path('media/2026/08/already-fine.jpg');
        $directory = Storage::disk('public')->path('media/2026/08');

        chmod($unreadable, 0600);
        chmod($directory, 0750);

        $this->assertSame(0, Artisan::call('media:fix-permissions', ['--disk' => 'public']));

        $this->assertSame(0644, $this->modeOf($unreadable));
        $this->assertSame(0755, $this->modeOf($directory));
        $this->assertSame(0644, $this->modeOf($readable), 'Files already servable must be left alone.');

        $output = Artisan::output();
        $this->assertStringContainsString('files fixed: 1', $output);
        $this->assertStringContainsString('directories fixed: 1', $output);
    }

    public function test_fix_permissions_command_never_narrows_an_existing_mode(): void
    {
        Storage::disk('public')->put('media/2026/08/wide-open.jpg', $this->makeJpegBytes(64, 64));

        $path = Storage::disk('public')->path('media/2026/08/wide-open.jpg');
        chmod($path, 0664);

        Artisan::call('media:fix-permissions', ['--disk' => 'public']);

        $this->assertSame(0664, $this->modeOf($path));
    }

    public function test_fix_permissions_command_reports_without_changing_anything_on_a_dry_run(): void
    {
        Storage::disk('public')->put('media/2026/08/dry-run.jpg', $this->makeJpegBytes(64, 64));

        $path = Storage::disk('public')->path('media/2026/08/dry-run.jpg');
        chmod($path, 0600);

        Artisan::call('media:fix-permissions', ['--disk' => 'public', '--dry-run' => true]);

        $this->assertSame(0600, $this->modeOf($path));
        $this->assertStringContainsString('files fixed: 1', Artisan::output());
    }

    public function test_fix_permissions_command_can_be_scoped_to_a_path_prefix(): void
    {
        Storage::disk('public')->put('media/2026/08/in-scope.jpg', $this->makeJpegBytes(64, 64));
        Storage::disk('public')->put('imports/pictime/out-of-scope.jpg', $this->makeJpegBytes(64, 64));

        $inScope = Storage::disk('public')->path('media/2026/08/in-scope.jpg');
        $outOfScope = Storage::disk('public')->path('imports/pictime/out-of-scope.jpg');

        chmod($inScope, 0600);
        chmod($outOfScope, 0600);

        Artisan::call('media:fix-permissions', ['--disk' => 'public', '--path-prefix' => 'media']);

        $this->assertSame(0644, $this->modeOf($inScope));
        $this->assertSame(0600, $this->modeOf($outOfScope));
    }

    public function test_fix_permissions_command_refuses_a_non_local_disk(): void
    {
        config(['filesystems.disks.s3.driver' => 's3']);

        $this->assertSame(1, Artisan::call('media:fix-permissions', ['--disk' => 's3']));
        $this->assertStringContainsString('not local', Artisan::output());
    }

    private function assertFileIsWorldReadable(string $absolutePath, string $message = ''): void
    {
        $this->assertFileExists($absolutePath);

        $mode = $this->modeOf($absolutePath);

        $this->assertSame(
            0044,
            $mode & 0044,
            ($message !== '' ? $message.' ' : '')
                .'Mode was '.decoct($mode).' for '.$absolutePath.'.'
        );
    }

    private function modeOf(string $absolutePath): int
    {
        clearstatcache(true, $absolutePath);

        return (int) (fileperms($absolutePath) & 0777);
    }

    private function makeJpegBytes(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);

        if (! $image instanceof \GdImage) {
            throw new \RuntimeException('Test image could not be created.');
        }

        try {
            for ($y = 0; $y < $height; $y++) {
                $color = imagecolorallocate($image, (int) round(255 * ($y / max(1, $height - 1))), 96, 160);
                imageline($image, 0, $y, $width, $y, $color);
            }

            ob_start();
            imagejpeg($image, null, 100);

            return (string) ob_get_clean();
        } finally {
            imagedestroy($image);
        }
    }
}
