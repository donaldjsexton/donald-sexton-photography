<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeneratePageSeoCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.anthropic.key', 'test-key');
        config()->set('services.anthropic.model', 'claude-haiku-4-5-20251001');
        config()->set('services.anthropic.version', '2023-06-01');
    }

    public function test_fills_empty_seo_fields_for_each_page(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push($this->fakeToolPayload('First Title', 'First description, long enough to read like a real meta description from the model.'))
                ->push($this->fakeToolPayload('Second Title', 'Second description, long enough to read like a real meta description from the model.')),
        ]);

        $first = Page::create([
            'title' => 'First Page',
            'slug' => 'first-page',
            'status' => 'published',
            'template' => 'custom',
        ]);

        $second = Page::create([
            'title' => 'Second Page',
            'slug' => 'second-page',
            'status' => 'published',
            'template' => 'custom',
        ]);

        $this->artisan('seo:generate-pages', ['--sleep' => 0])
            ->assertSuccessful();

        $this->assertSame('First Title', $first->fresh()->seo_title);
        $this->assertSame('Second Title', $second->fresh()->seo_title);
    }

    public function test_skips_pages_that_already_have_seo_unless_all_flag(): void
    {
        $alreadyFilled = Page::create([
            'title' => 'Filled',
            'slug' => 'filled',
            'status' => 'published',
            'template' => 'custom',
            'seo_title' => 'Existing',
            'seo_description' => 'Existing description that we do not want overwritten by the default run.',
        ]);

        $needsSeo = Page::create([
            'title' => 'Needs SEO',
            'slug' => 'needs-seo',
            'status' => 'published',
            'template' => 'custom',
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload('New Title', 'New description, long enough to read like a real meta description from the model.')),
        ]);

        $this->artisan('seo:generate-pages', ['--sleep' => 0])->assertSuccessful();

        $this->assertSame('Existing', $alreadyFilled->fresh()->seo_title);
        $this->assertSame('New Title', $needsSeo->fresh()->seo_title);

        Http::assertSentCount(1);
    }

    public function test_partial_seo_fills_missing_field_without_overwriting_existing(): void
    {
        $page = Page::create([
            'title' => 'Hand-edited title',
            'slug' => 'partial',
            'status' => 'published',
            'template' => 'custom',
            'seo_title' => 'Hand-edited SEO title that we keep',
            'seo_description' => null,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload(
                'Generated Title We Should Discard',
                'Generated description that should be saved because the existing one is empty.',
            )),
        ]);

        $this->artisan('seo:generate-pages', ['--sleep' => 0])->assertSuccessful();

        $fresh = $page->fresh();

        $this->assertSame('Hand-edited SEO title that we keep', $fresh->seo_title);
        $this->assertSame(
            'Generated description that should be saved because the existing one is empty.',
            $fresh->seo_description,
        );
    }

    public function test_all_flag_regenerates_filled_pages(): void
    {
        $page = Page::create([
            'title' => 'Filled',
            'slug' => 'filled',
            'status' => 'published',
            'template' => 'custom',
            'seo_title' => 'Old Title',
            'seo_description' => 'Old description.',
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload('Refreshed Title', 'Refreshed description, long enough to read like a real meta description from the model.')),
        ]);

        $this->artisan('seo:generate-pages', ['--all' => true, '--sleep' => 0])->assertSuccessful();

        $this->assertSame('Refreshed Title', $page->fresh()->seo_title);
    }

    public function test_dry_run_does_not_call_api_or_save(): void
    {
        Http::fake();

        $page = Page::create([
            'title' => 'Needs SEO',
            'slug' => 'needs-seo',
            'status' => 'published',
            'template' => 'custom',
        ]);

        $this->artisan('seo:generate-pages', ['--dry-run' => true, '--sleep' => 0])->assertSuccessful();

        $this->assertNull($page->fresh()->seo_title);
        Http::assertNothingSent();
    }

    public function test_page_option_targets_specific_ids(): void
    {
        $target = Page::create([
            'title' => 'Target',
            'slug' => 'target',
            'status' => 'published',
            'template' => 'custom',
        ]);

        $other = Page::create([
            'title' => 'Other',
            'slug' => 'other',
            'status' => 'published',
            'template' => 'custom',
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload('Targeted Title', 'Targeted description, long enough to read like a real meta description from the model.')),
        ]);

        $this->artisan('seo:generate-pages', ['--page' => [$target->id], '--sleep' => 0])->assertSuccessful();

        $this->assertSame('Targeted Title', $target->fresh()->seo_title);
        $this->assertNull($other->fresh()->seo_title);
        Http::assertSentCount(1);
    }

    public function test_limit_caps_number_of_pages_processed(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            Page::create([
                'title' => "Page {$i}",
                'slug' => "page-{$i}",
                'status' => 'published',
                'template' => 'custom',
            ]);
        }

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload(
                'Title',
                'Description that is long enough to look like a real meta description from the model.',
            )),
        ]);

        $this->artisan('seo:generate-pages', ['--limit' => 2, '--sleep' => 0])->assertSuccessful();

        Http::assertSentCount(2);
        $this->assertSame(2, Page::whereNotNull('seo_title')->count());
    }

    public function test_reports_no_work_when_nothing_to_generate(): void
    {
        Page::create([
            'title' => 'Filled',
            'slug' => 'filled',
            'status' => 'published',
            'template' => 'custom',
            'seo_title' => 'Title',
            'seo_description' => 'Description.',
        ]);

        Http::fake();

        $this->artisan('seo:generate-pages', ['--sleep' => 0])
            ->expectsOutputToContain('No pages need SEO generation.')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    /**
     * @return array<string, mixed>
     */
    private function fakeToolPayload(string $title, string $description): array
    {
        return [
            'content' => [[
                'type' => 'tool_use',
                'name' => 'write_page_seo',
                'input' => [
                    'title' => $title,
                    'description' => $description,
                ],
            ]],
        ];
    }
}
