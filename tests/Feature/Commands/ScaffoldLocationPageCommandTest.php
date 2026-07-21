<?php

namespace Tests\Feature\Commands;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScaffoldLocationPageCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_drafts_a_location_page(): void
    {
        $exit = Artisan::call('seo:scaffold-location', ['city' => 'Tampa', '--region' => 'Tampa Bay']);

        $this->assertSame(0, $exit);

        $page = Page::query()->where('slug', 'tampa')->first();

        $this->assertNotNull($page);
        $this->assertSame('location', $page->template);
        $this->assertSame('draft', $page->status);
        $this->assertSame('Tampa Wedding Photographer', $page->title);
        // Brand suffix is appended centrally by the layout, so the stored
        // seo_title must not carry it.
        $this->assertSame('Tampa Wedding Photographer', $page->seo_title);
        $this->assertStringNotContainsString('Donald Sexton', (string) $page->seo_title);
        $this->assertNotEmpty($page->faqs);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $exit = Artisan::call('seo:scaffold-location', ['city' => 'St. Petersburg', '--dry-run' => true]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Dry run complete', Artisan::output());
        $this->assertSame(0, Page::query()->count());
    }

    public function test_slug_collisions_are_suffixed(): void
    {
        Artisan::call('seo:scaffold-location', ['city' => 'Clearwater']);
        Artisan::call('seo:scaffold-location', ['city' => 'Clearwater']);

        $this->assertTrue(Page::query()->where('slug', 'clearwater')->exists());
        $this->assertTrue(Page::query()->where('slug', 'clearwater-2')->exists());
    }
}
