<?php

namespace Tests\Feature\Commands;

use App\Models\JournalPost;
use App\Models\Redirect;
use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AuditLegacyRedirectsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function publishStory(string $slug): WeddingStory
    {
        return WeddingStory::query()->create([
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => 'published',
            'story_type' => 'wedding',
            'published_at' => now()->subDay(),
        ]);
    }

    private function publishPost(string $slug): JournalPost
    {
        return JournalPost::query()->create([
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_it_reports_covered_and_missing_legacy_paths(): void
    {
        $this->publishStory('barquero-wedding');
        Redirect::query()->create([
            'from_path' => '/barquero-wedding',
            'to_path' => '/weddings/barquero-wedding',
            'status_code' => 301,
        ]);

        $this->publishStory('mason-wedding'); // no redirect

        $exit = Artisan::call('seo:audit-redirects');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('/barquero-wedding → /weddings/barquero-wedding', $output);
        $this->assertStringContainsString('no redirect for /mason-wedding', $output);
        $this->assertStringContainsString('Legacy redirects present: 1. Missing: 1.', $output);
    }

    public function test_it_flags_off_topic_ipad_slug_under_weddings(): void
    {
        $this->publishStory('are-ipads-good-for-photo-editing');

        Artisan::call('seo:audit-redirects');
        $output = Artisan::output();

        $this->assertStringContainsString('off-topic slug under /weddings', $output);
    }

    public function test_missing_only_hides_covered_records(): void
    {
        $this->publishPost('the-ultimate-guide-to-wedding-florals');
        Redirect::query()->create([
            'from_path' => '/the-ultimate-guide-to-wedding-florals',
            'to_path' => '/journal/the-ultimate-guide-to-wedding-florals',
            'status_code' => 301,
        ]);

        Artisan::call('seo:audit-redirects', ['--missing-only' => true]);
        $output = Artisan::output();

        $this->assertStringNotContainsString('→ /journal/the-ultimate-guide-to-wedding-florals', $output);
    }
}
