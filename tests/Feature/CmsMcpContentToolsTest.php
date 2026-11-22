<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\CmsMcpCredential;
use App\Models\JournalPost;
use App\Models\Media;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Venue;
use App\Models\WeddingStory;
use App\Services\Media\AltTextGenerator;
use App\Services\Seo\GeneratedSeo;
use App\Services\Seo\PageSeoGenerator;
use Illuminate\Support\Facades\Log;

class CmsMcpContentToolsTest extends CmsMcpTestCase
{
    public function test_tools_list_follows_abilities_and_default_grants_stay_small(): void
    {
        $default = $this->toolNames($this->token(CmsMcpCredential::DEFAULT_ABILITIES));
        $this->assertSame(['lookup_story', 'create_draft_story', 'update_draft_story', 'upload_media', 'generate_seo'], $default);

        $reader = $this->toolNames($this->token([CmsMcpCredential::SITE_READ]));
        $this->assertEqualsCanonicalizing([
            'list_pages', 'get_page', 'list_journal_posts', 'get_journal_post', 'list_venues', 'get_venue',
            'list_media', 'list_redirects', 'get_homepage',
        ], $reader);

        $pages = $this->toolNames($this->token([CmsMcpCredential::PAGES_WRITE]));
        $this->assertContains('create_draft_page', $pages);
        $this->assertNotContains('publish_page', $pages);
        $this->assertNotContains('create_draft_journal_post', $pages);

        foreach (CmsMcpCredential::DEFAULT_ABILITIES as $ability) {
            $this->assertNotContains($ability, [
                CmsMcpCredential::PUBLISH, CmsMcpCredential::PAGES_PUBLISH, CmsMcpCredential::JOURNAL_PUBLISH,
                CmsMcpCredential::SETTINGS_WRITE, CmsMcpCredential::FINANCE_READ,
            ]);
        }
    }

    public function test_hidden_write_tools_do_not_change_data(): void
    {
        $reader = $this->token([CmsMcpCredential::SITE_READ]);

        $this->tool($reader, 'create_draft_page', ['title' => 'Nope'])->assertJsonPath('error.code', -32602);
        $this->tool($reader, 'create_draft_journal_post', ['title' => 'Nope'])->assertJsonPath('error.code', -32602);
        $this->tool($reader, 'create_redirect', ['from_path' => '/a', 'to_path' => '/b'])->assertJsonPath('error.code', -32602);

        $this->assertSame(0, Page::query()->count());
        $this->assertSame(0, JournalPost::query()->count());
        $this->assertSame(0, Redirect::query()->count());
    }

    public function test_page_drafts_stay_draft_until_the_publish_tool(): void
    {
        $writer = $this->token([CmsMcpCredential::PAGES_WRITE, CmsMcpCredential::SITE_READ]);
        $publisher = $this->token([CmsMcpCredential::PAGES_PUBLISH]);

        $created = $this->tool($writer, 'create_draft_page', [
            'title' => 'Pricing notes',
            'body' => '<p>Hello</p>',
            'status' => 'published',
            'published_at' => '2026-01-01',
            'faqs_text' => 'Q one | A one',
        ])->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.page.status', 'draft')
            ->assertJsonPath('result.structuredContent.page.slug', 'pricing-notes')
            ->assertJsonPath('result.structuredContent.page.template', 'custom');

        $id = (int) $created->json('result.structuredContent.page.id');
        $this->assertNull(Page::query()->find($id)->published_at);

        $this->tool($writer, 'update_page', ['page_id' => $id, 'seo_title' => 'SEO', 'status' => 'published'])
            ->assertJsonPath('result.structuredContent.page.status', 'draft')
            ->assertJsonPath('result.structuredContent.page.body', '<p>Hello</p>')
            ->assertJsonPath('result.structuredContent.page.seo_title', 'SEO');

        $this->tool($writer, 'update_page', ['page_id' => $id, 'template' => 'bogus'])
            ->assertJsonPath('result.isError', true);

        $this->tool($writer, 'publish_page', ['page_id' => $id])->assertJsonPath('error.code', -32602);
        $this->assertSame('draft', Page::query()->find($id)->status);

        $this->tool($publisher, 'publish_page', ['page_id' => $id])
            ->assertJsonPath('result.structuredContent.page.status', 'published');

        $this->tool($writer, 'update_page', ['page_id' => $id, 'body' => '<p>Live edit</p>'])
            ->assertJsonPath('result.isError', true);
        $this->assertSame('<p>Hello</p>', Page::query()->find($id)->body);
    }

    public function test_published_page_can_be_returned_to_draft_and_edited(): void
    {
        $writer = $this->token([CmsMcpCredential::PAGES_WRITE]);
        $publisher = $this->token([CmsMcpCredential::PAGES_PUBLISH]);

        $id = (int) $this->tool($writer, 'create_draft_page', ['title' => 'Back', 'body' => '<p>One</p>'])->json('result.structuredContent.page.id');
        $this->tool($publisher, 'publish_page', ['page_id' => $id]);
        $this->tool($writer, 'unpublish_page', ['page_id' => $id])->assertJsonPath('error.code', -32602);

        $this->tool($publisher, 'unpublish_page', ['page_id' => $id])
            ->assertJsonPath('result.structuredContent.page.status', 'draft');
        $this->tool($writer, 'update_page', ['page_id' => $id, 'body' => '<p>Two</p>'])
            ->assertJsonPath('result.isError', false);
        $this->assertSame('<p>Two</p>', Page::query()->find($id)->body);
    }

    public function test_draft_pages_can_be_deleted_but_published_ones_cannot(): void
    {
        $writer = $this->token([CmsMcpCredential::PAGES_WRITE]);
        $publisher = $this->token([CmsMcpCredential::PAGES_PUBLISH]);

        $draft = (int) $this->tool($writer, 'create_draft_page', ['title' => 'Gone'])->json('result.structuredContent.page.id');
        $this->tool($writer, 'delete_draft_page', ['page_id' => $draft])->assertJsonPath('result.structuredContent.deleted', true);
        $this->assertNull(Page::query()->find($draft));

        $kept = (int) $this->tool($writer, 'create_draft_page', ['title' => 'Kept'])->json('result.structuredContent.page.id');
        $this->tool($publisher, 'publish_page', ['page_id' => $kept]);
        $this->tool($writer, 'delete_draft_page', ['page_id' => $kept])->assertJsonPath('result.isError', true);
        $this->assertNotNull(Page::query()->find($kept));
    }

    public function test_page_slug_must_be_unique_like_the_admin_form(): void
    {
        Page::factory()->create(['slug' => 'taken']);
        $writer = $this->token([CmsMcpCredential::PAGES_WRITE]);

        $this->tool($writer, 'create_draft_page', ['title' => 'Other', 'slug' => 'taken'])
            ->assertJsonPath('result.isError', true);
        $this->assertSame(1, Page::query()->count());
    }

    public function test_page_blocks_can_be_created_updated_and_reordered_on_drafts_only(): void
    {
        $writer = $this->token([CmsMcpCredential::PAGES_WRITE, CmsMcpCredential::SITE_READ]);
        $draft = Page::factory()->create(['status' => 'draft']);
        $live = Page::factory()->create(['status' => 'published']);
        $type = array_key_first(array_filter((array) config('blocks.types'), fn (array $d): bool => ($d['context'] ?? 'page') !== 'homepage'));

        $first = $this->tool($writer, 'create_page_block', ['page_id' => $draft->id, 'type' => $type, 'heading' => 'One'])
            ->assertJsonPath('result.isError', false);
        $second = $this->tool($writer, 'create_page_block', ['page_id' => $draft->id, 'type' => $type, 'heading' => 'Two']);
        $a = (int) $first->json('result.structuredContent.block.id');
        $b = (int) $second->json('result.structuredContent.block.id');

        $this->tool($writer, 'update_page_block', ['page_id' => $draft->id, 'block_id' => $a, 'type' => $type, 'heading' => 'Uno', 'is_visible' => true])
            ->assertJsonPath('result.structuredContent.block.heading', 'Uno');

        $this->tool($writer, 'create_page_block', ['page_id' => $draft->id, 'type' => 'not-a-block'])
            ->assertJsonPath('result.isError', true);

        $this->tool($writer, 'reorder_page_blocks', ['page_id' => $draft->id, 'block_ids' => [$b, $a]])->assertJsonPath('result.isError', false);
        $read = $this->tool($writer, 'get_page', ['page_id' => $draft->id]);
        $this->assertSame([$b, $a], array_column($read->json('result.structuredContent.page.blocks'), 'id'));

        $this->tool($writer, 'create_page_block', ['page_id' => $live->id, 'type' => $type])->assertJsonPath('result.isError', true);
        $this->assertSame(0, Block::query()->where('blockable_id', $live->id)->count());

        $foreign = Block::query()->where('blockable_id', $draft->id)->first();
        $this->tool($writer, 'update_page_block', ['page_id' => Page::factory()->create(['status' => 'draft'])->id, 'block_id' => $foreign->id, 'type' => $type])
            ->assertJsonPath('result.isError', true);
    }

    public function test_draft_journal_posts_can_be_deleted_but_published_ones_cannot(): void
    {
        $writer = $this->token([CmsMcpCredential::JOURNAL_WRITE]);
        $publisher = $this->token([CmsMcpCredential::JOURNAL_PUBLISH]);

        $draft = (int) $this->tool($writer, 'create_draft_journal_post', ['title' => 'Gone', 'post_type' => 'venue'])->json('result.structuredContent.post.id');
        $this->tool($writer, 'delete_draft_journal_post', ['post_id' => $draft])->assertJsonPath('result.structuredContent.deleted', true);
        $this->assertNull(JournalPost::query()->find($draft));

        $kept = (int) $this->tool($writer, 'create_draft_journal_post', ['title' => 'Kept', 'post_type' => 'venue'])->json('result.structuredContent.post.id');
        $this->tool($publisher, 'publish_journal_post', ['post_id' => $kept]);
        $this->tool($writer, 'delete_draft_journal_post', ['post_id' => $kept])->assertJsonPath('result.isError', true);
        $this->assertNotNull(JournalPost::query()->find($kept));
    }

    public function test_published_journal_posts_can_be_deleted_only_with_publish_ability_and_slug_confirmation(): void
    {
        $writer = $this->token([CmsMcpCredential::JOURNAL_WRITE]);
        $publisher = $this->token([CmsMcpCredential::JOURNAL_PUBLISH]);

        $id = (int) $this->tool($writer, 'create_draft_journal_post', ['title' => 'Dupe', 'slug' => 'dupe', 'post_type' => 'venue'])->json('result.structuredContent.post.id');

        $this->tool($publisher, 'delete_published_journal_post', ['post_id' => $id, 'confirm_slug' => 'dupe'])->assertJsonPath('result.isError', true);
        $this->tool($publisher, 'publish_journal_post', ['post_id' => $id]);

        $this->tool($writer, 'delete_published_journal_post', ['post_id' => $id, 'confirm_slug' => 'dupe'])->assertJsonPath('error.code', -32602);
        $this->tool($publisher, 'delete_published_journal_post', ['post_id' => $id, 'confirm_slug' => 'wrong'])->assertJsonPath('result.isError', true);
        $this->assertNotNull(JournalPost::query()->find($id));

        $this->tool($publisher, 'delete_published_journal_post', ['post_id' => $id, 'confirm_slug' => 'dupe'])->assertJsonPath('result.structuredContent.deleted', true);
        $this->assertNull(JournalPost::query()->find($id));
    }

    public function test_journal_posts_are_drafts_until_published_with_the_separate_ability(): void
    {
        $writer = $this->token([CmsMcpCredential::JOURNAL_WRITE]);
        $publisher = $this->token([CmsMcpCredential::JOURNAL_PUBLISH]);

        $created = $this->tool($writer, 'create_draft_journal_post', ['title' => 'Venue tips', 'status' => 'published', 'post_type' => 'venue'])
            ->assertJsonPath('result.structuredContent.post.status', 'draft')
            ->assertJsonPath('result.structuredContent.post.post_type', 'venue');
        $id = (int) $created->json('result.structuredContent.post.id');

        $this->tool($writer, 'update_journal_post', ['post_id' => $id, 'body' => '<p>Body</p>', 'excerpt' => 'Ex'])
            ->assertJsonPath('result.structuredContent.post.body', '<p>Body</p>')
            ->assertJsonPath('result.structuredContent.post.status', 'draft');

        $this->tool($writer, 'publish_journal_post', ['post_id' => $id])->assertJsonPath('error.code', -32602);
        $this->assertSame('draft', JournalPost::query()->find($id)->status);

        $this->tool($publisher, 'publish_journal_post', ['post_id' => $id])
            ->assertJsonPath('result.structuredContent.post.status', 'published');
        $this->assertNotNull(JournalPost::query()->find($id)->published_at);

        $this->tool($writer, 'update_journal_post', ['post_id' => $id, 'body' => 'changed'])->assertJsonPath('result.isError', true);
        $this->assertSame('<p>Body</p>', JournalPost::query()->find($id)->body);
    }

    public function test_venue_update_changes_only_public_fields_and_hides_billing(): void
    {
        $venue = Venue::factory()->create(['name' => 'Old', 'billing_email' => 'billing@example.test', 'is_featured' => true]);
        $writer = $this->token([CmsMcpCredential::VENUES_WRITE, CmsMcpCredential::SITE_READ]);

        $response = $this->tool($writer, 'update_venue', [
            'venue_id' => $venue->id,
            'headline' => 'New headline',
            'billing_email' => 'attacker@example.test',
            'portal_password' => 'changeme-now',
        ])->assertJsonPath('result.structuredContent.venue.headline', 'New headline')
            ->assertJsonPath('result.structuredContent.venue.name', 'Old')
            ->assertJsonPath('result.structuredContent.venue.is_featured', true);

        $this->assertStringNotContainsString('billing', $response->getContent());
        $venue->refresh();
        $this->assertSame('billing@example.test', $venue->billing_email);
        $this->assertSame('New headline', $venue->headline);

        $read = $this->tool($writer, 'get_venue', ['venue_id' => $venue->id]);
        $this->assertStringNotContainsString('billing@example.test', $read->getContent());
        $this->tool($this->token([CmsMcpCredential::SITE_READ]), 'update_venue', ['venue_id' => $venue->id, 'headline' => 'x'])
            ->assertJsonPath('error.code', -32602);
    }

    public function test_media_alt_text_and_attach_to_draft_owners(): void
    {
        $media = Media::query()->create(['disk' => 'public', 'path' => 'x/a.jpg', 'filename' => 'a.jpg']);
        $writer = $this->token([CmsMcpCredential::MEDIA_WRITE, CmsMcpCredential::SITE_READ]);
        $draftStory = WeddingStory::query()->create(['title' => 'S', 'slug' => 's', 'status' => 'draft']);
        $liveStory = WeddingStory::query()->create(['title' => 'L', 'slug' => 'l', 'status' => 'published']);
        $page = Page::factory()->create(['status' => 'draft']);

        $this->tool($writer, 'list_media', ['missing_alt' => true])->assertJsonPath('result.structuredContent.total', 1);
        $this->tool($writer, 'update_media_alt_text', ['media_id' => $media->id, 'alt_text' => 'Couple at dusk'])
            ->assertJsonPath('result.structuredContent.media.alt_text', 'Couple at dusk');
        $this->tool($writer, 'list_media', ['missing_alt' => true])->assertJsonPath('result.structuredContent.total', 0);

        $this->tool($writer, 'attach_media', ['media_id' => $media->id, 'owner_type' => 'story', 'owner_id' => $draftStory->id])
            ->assertJsonPath('result.isError', false);
        $this->tool($writer, 'attach_media', ['media_id' => $media->id, 'owner_type' => 'page', 'owner_id' => $page->id])
            ->assertJsonPath('result.isError', false);
        $this->tool($writer, 'attach_media', ['media_id' => $media->id, 'owner_type' => 'story', 'owner_id' => $liveStory->id])
            ->assertJsonPath('result.isError', true);

        $this->assertSame(1, $draftStory->media()->count());
        $this->assertSame(1, $page->media()->count());
        $this->assertSame(0, $liveStory->media()->count());

        $this->tool($this->token([CmsMcpCredential::SITE_READ]), 'update_media_alt_text', ['media_id' => $media->id, 'alt_text' => 'x'])
            ->assertJsonPath('error.code', -32602);
        $this->assertSame('Couple at dusk', $media->refresh()->alt_text);
    }

    public function test_redirects_validate_paths_and_refuse_external_targets(): void
    {
        $writer = $this->token([CmsMcpCredential::REDIRECTS_WRITE, CmsMcpCredential::SITE_READ]);

        $created = $this->tool($writer, 'create_redirect', ['from_path' => '/old', 'to_path' => '/new'])
            ->assertJsonPath('result.structuredContent.redirect.status_code', 301)
            ->assertJsonPath('result.structuredContent.redirect.source', 'cms-mcp');
        $id = (int) $created->json('result.structuredContent.redirect.id');

        foreach ([
            ['from_path' => '/old', 'to_path' => '/other'],
            ['from_path' => '/x', 'to_path' => 'https://evil.example/'],
            ['from_path' => '/x', 'to_path' => '//evil.example'],
            ['from_path' => '/x', 'to_path' => '/x'],
            ['from_path' => '/y', 'to_path' => '/z', 'status_code' => 200],
        ] as $bad) {
            $this->tool($writer, 'create_redirect', $bad)->assertJsonPath('result.isError', true);
        }

        $this->assertSame(1, Redirect::query()->count());

        $this->tool($writer, 'update_redirect', ['redirect_id' => $id, 'to_path' => '/newer', 'status_code' => 302])
            ->assertJsonPath('result.structuredContent.redirect.to_path', '/newer')
            ->assertJsonPath('result.structuredContent.redirect.from_path', '/old');
        $this->tool($writer, 'update_redirect', ['redirect_id' => $id, 'to_path' => 'https://evil.example'])
            ->assertJsonPath('result.isError', true);
        $this->tool($writer, 'list_redirects')->assertJsonPath('result.structuredContent.total', 1);
    }

    public function test_generate_tools_use_the_existing_generators_and_respect_drafts(): void
    {
        $this->mock(PageSeoGenerator::class, fn ($mock) => $mock->shouldReceive('generate')->andReturn(new GeneratedSeo('Gen title', 'Gen description')));
        $this->mock(AltTextGenerator::class, fn ($mock) => $mock->shouldReceive('generate')->andReturn('Generated alt'));

        $draft = Page::factory()->create(['status' => 'draft', 'seo_title' => 'Kept', 'seo_description' => null]);
        $live = Page::factory()->create(['status' => 'published', 'seo_title' => null, 'seo_description' => null]);
        $media = Media::query()->create(['disk' => 'public', 'path' => 'x/b.jpg', 'filename' => 'b.jpg', 'alt_text' => 'Existing']);
        $token = $this->token([CmsMcpCredential::PAGES_WRITE, CmsMcpCredential::MEDIA_WRITE]);

        $this->tool($token, 'generate_seo', ['type' => 'page', 'id' => $draft->id])
            ->assertJsonPath('result.structuredContent.updated', ['seo_description']);
        $draft->refresh();
        $this->assertSame('Kept', $draft->seo_title);
        $this->assertSame('Gen description', $draft->seo_description);

        $this->tool($token, 'generate_seo', ['type' => 'page', 'id' => $live->id])->assertJsonPath('result.isError', true);
        $this->assertNull($live->refresh()->seo_title);

        $this->tool($token, 'generate_seo', ['type' => 'journal_post', 'id' => 1])->assertJsonPath('result.isError', true);

        $this->tool($token, 'generate_alt_text', ['media_id' => $media->id])->assertJsonPath('result.structuredContent.generated', false);
        $this->assertSame('Existing', $media->refresh()->alt_text);
        $this->tool($token, 'generate_alt_text', ['media_id' => $media->id, 'overwrite' => true])->assertJsonPath('result.structuredContent.generated', true);
        $this->assertSame('Generated alt', $media->refresh()->alt_text);
    }

    public function test_seo_generation_checks_the_ability_for_the_content_type(): void
    {
        $page = Page::factory()->create(['status' => 'draft']);
        $storyOnly = $this->token([CmsMcpCredential::DRAFT]);

        $this->tool($storyOnly, 'generate_seo', ['type' => 'page', 'id' => $page->id])->assertJsonPath('result.isError', true);
        $this->assertNull($page->refresh()->seo_title);
    }

    public function test_every_tool_call_is_logged_with_the_credential_name_and_no_secrets(): void
    {
        Log::spy();
        $issued = CmsMcpCredential::issue('Audit Bot', [CmsMcpCredential::SITE_READ]);

        $this->tool($issued['token'], 'list_pages', ['search' => 'secret-argument']);

        Log::shouldHaveReceived('log')->withArgs(function (string $level, string $message, array $context) use ($issued): bool {
            $dump = json_encode($context);

            return $message === 'cms_mcp.tool_call'
                && $context['credential'] === 'Audit Bot'
                && $context['tool'] === 'list_pages'
                && $context['allowed'] === true
                && ! str_contains((string) $dump, $issued['token'])
                && ! str_contains((string) $dump, 'secret-argument');
        })->once();
    }
}
