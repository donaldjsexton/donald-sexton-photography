<?php

namespace Tests\Feature\Console\Commands;

use App\Models\WeddingStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerateWeddingStorySeoCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.anthropic.key', 'test-key');
        config()->set('services.anthropic.model', 'claude-haiku-4-5-20251001');
        config()->set('services.anthropic.version', '2023-06-01');
    }

    public function test_fills_empty_seo_fields_for_each_story(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push($this->fakeToolPayload('First Title', 'First description, long enough to read like a real meta description from the model.'))
                ->push($this->fakeToolPayload('Second Title', 'Second description, long enough to read like a real meta description from the model.')),
        ]);

        $first = WeddingStory::create([
            'title' => 'First Story',
            'slug' => 'first-story',
            'status' => 'published',
        ]);

        $second = WeddingStory::create([
            'title' => 'Second Story',
            'slug' => 'second-story',
            'status' => 'published',
        ]);

        $this->artisan('seo:generate-wedding-stories', ['--sleep' => 0])
            ->assertSuccessful();

        $this->assertSame('First Title', $first->fresh()->seo_title);
        $this->assertSame('Second Title', $second->fresh()->seo_title);
    }

    public function test_skips_stories_that_already_have_seo_unless_all_flag(): void
    {
        $alreadyFilled = WeddingStory::create([
            'title' => 'Filled',
            'slug' => 'filled',
            'status' => 'published',
            'seo_title' => 'Existing',
            'seo_description' => 'Existing description that we do not want overwritten by the default run.',
        ]);

        $needsSeo = WeddingStory::create([
            'title' => 'Needs SEO',
            'slug' => 'needs-seo',
            'status' => 'published',
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload('New Title', 'New description, long enough to read like a real meta description from the model.')),
        ]);

        $this->artisan('seo:generate-wedding-stories', ['--sleep' => 0])->assertSuccessful();

        $this->assertSame('Existing', $alreadyFilled->fresh()->seo_title);
        $this->assertSame('New Title', $needsSeo->fresh()->seo_title);

        Http::assertSentCount(1);
    }

    public function test_partial_seo_fills_missing_field_without_overwriting_existing(): void
    {
        $story = WeddingStory::create([
            'title' => 'Hand-edited title',
            'slug' => 'partial',
            'status' => 'published',
            'seo_title' => 'Hand-edited SEO title that we keep',
            'seo_description' => null,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload(
                'Generated Title We Should Discard',
                'Generated description that should be saved because the existing one is empty.',
            )),
        ]);

        $this->artisan('seo:generate-wedding-stories', ['--sleep' => 0])->assertSuccessful();

        $fresh = $story->fresh();

        $this->assertSame('Hand-edited SEO title that we keep', $fresh->seo_title);
        $this->assertSame(
            'Generated description that should be saved because the existing one is empty.',
            $fresh->seo_description,
        );
    }

    public function test_partial_seo_treats_empty_string_like_null(): void
    {
        $story = WeddingStory::create([
            'title' => 'Empty string side',
            'slug' => 'empty-side',
            'status' => 'published',
            'seo_title' => '',
            'seo_description' => 'Hand written description that we keep',
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload(
                'Generated Title For Empty Slot',
                'Generated description that we should NOT save because there is one already.',
            )),
        ]);

        $this->artisan('seo:generate-wedding-stories', ['--sleep' => 0])->assertSuccessful();

        $fresh = $story->fresh();

        $this->assertSame('Generated Title For Empty Slot', $fresh->seo_title);
        $this->assertSame('Hand written description that we keep', $fresh->seo_description);
    }

    public function test_all_flag_regenerates_filled_stories(): void
    {
        $story = WeddingStory::create([
            'title' => 'Filled',
            'slug' => 'filled',
            'status' => 'published',
            'seo_title' => 'Old Title',
            'seo_description' => 'Old description.',
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload('Refreshed Title', 'Refreshed description, long enough to read like a real meta description from the model.')),
        ]);

        $this->artisan('seo:generate-wedding-stories', ['--all' => true, '--sleep' => 0])->assertSuccessful();

        $this->assertSame('Refreshed Title', $story->fresh()->seo_title);
    }

    public function test_dry_run_does_not_call_api_or_save(): void
    {
        Http::fake();

        $story = WeddingStory::create([
            'title' => 'Needs SEO',
            'slug' => 'needs-seo',
            'status' => 'published',
        ]);

        $this->artisan('seo:generate-wedding-stories', ['--dry-run' => true, '--sleep' => 0])->assertSuccessful();

        $this->assertNull($story->fresh()->seo_title);
        Http::assertNothingSent();
    }

    public function test_story_option_targets_specific_ids(): void
    {
        $target = WeddingStory::create([
            'title' => 'Target',
            'slug' => 'target',
            'status' => 'published',
        ]);

        $other = WeddingStory::create([
            'title' => 'Other',
            'slug' => 'other',
            'status' => 'published',
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload('Targeted Title', 'Targeted description, long enough to read like a real meta description from the model.')),
        ]);

        $this->artisan('seo:generate-wedding-stories', ['--story' => [$target->id], '--sleep' => 0])->assertSuccessful();

        $this->assertSame('Targeted Title', $target->fresh()->seo_title);
        $this->assertNull($other->fresh()->seo_title);
        Http::assertSentCount(1);
    }

    public function test_limit_caps_number_of_stories_processed(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            WeddingStory::create([
                'title' => "Story {$i}",
                'slug' => "story-{$i}",
                'status' => 'published',
            ]);
        }

        Http::fake([
            'api.anthropic.com/*' => Http::response($this->fakeToolPayload(
                'Title',
                'Description that is long enough to look like a real meta description from the model.',
            )),
        ]);

        $this->artisan('seo:generate-wedding-stories', ['--limit' => 2, '--sleep' => 0])->assertSuccessful();

        Http::assertSentCount(2);
        $this->assertSame(2, WeddingStory::whereNotNull('seo_title')->count());
    }

    public function test_reports_no_work_when_nothing_to_generate(): void
    {
        WeddingStory::create([
            'title' => 'Filled',
            'slug' => 'filled',
            'status' => 'published',
            'seo_title' => 'Title',
            'seo_description' => 'Description.',
        ]);

        Http::fake();

        $this->artisan('seo:generate-wedding-stories', ['--sleep' => 0])
            ->expectsOutputToContain('No wedding stories need SEO generation.')
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
                'name' => 'write_wedding_story_seo',
                'input' => [
                    'title' => $title,
                    'description' => $description,
                ],
            ]],
        ];
    }
}
