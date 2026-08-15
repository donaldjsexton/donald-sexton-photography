<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The admin PWA advertises a web app manifest and icons in its <head>. When
 * the icon files are missing the browser logs manifest download errors and
 * the install prompt gets no artwork, so guard that the declared assets exist
 * and that the layout emits the standard (non-deprecated) capability meta.
 */
class PwaManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_icons_exist_as_valid_pngs_at_declared_sizes(): void
    {
        $manifestPath = public_path('manifest.json');

        $this->assertFileExists($manifestPath);

        $manifest = json_decode(File::get($manifestPath), true);

        $this->assertIsArray($manifest, 'manifest.json is not valid JSON.');
        $this->assertNotEmpty($manifest['icons'] ?? [], 'manifest.json declares no icons.');

        foreach ($manifest['icons'] as $icon) {
            $iconPath = public_path(ltrim($icon['src'], '/'));

            $this->assertFileExists($iconPath, "Manifest icon {$icon['src']} is missing from public/.");

            $dimensions = getimagesize($iconPath);

            $this->assertNotFalse($dimensions, "Manifest icon {$icon['src']} is not a valid image.");
            $this->assertSame('image/png', $dimensions['mime'], "Manifest icon {$icon['src']} is not a PNG.");

            [$expectedWidth, $expectedHeight] = array_map('intval', explode('x', $icon['sizes']));

            $this->assertSame($expectedWidth, $dimensions[0], "Manifest icon {$icon['src']} width does not match its declared size.");
            $this->assertSame($expectedHeight, $dimensions[1], "Manifest icon {$icon['src']} height does not match its declared size.");
        }
    }

    public function test_admin_layout_links_manifest_assets_and_standard_meta(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('<meta name="mobile-web-app-capable" content="yes">', false);
        $response->assertSee('<link rel="manifest" href="/manifest.json">', false);
        $response->assertSee('<link rel="apple-touch-icon" href="/icon-192.png">', false);
    }
}
