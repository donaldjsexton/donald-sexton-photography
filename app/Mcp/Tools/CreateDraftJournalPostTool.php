<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\JournalPost;
use App\Services\Cms\JournalPostManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class CreateDraftJournalPostTool extends CmsTool
{
    protected string $name = 'create_draft_journal_post';

    protected string $description = 'Create a journal post as a draft. The status is always draft and the post is never published here.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly JournalPostManager $posts,
        private readonly CmsPayloads $payloads,
    ) {
        parent::__construct($gate);
    }

    protected function ability(): string
    {
        return CmsMcpCredential::JOURNAL_WRITE;
    }

    protected function run(Request $request): ResponseFactory
    {
        $rules = Arr::except($this->posts->rules(), ['status', 'published_at', 'original_wp_post_id', 'original_wp_url']);
        $validated = $this->validated($request, $rules, ['post_type' => 'advice']);
        $validated['status'] = 'draft';
        $validated['published_at'] = null;

        $post = new JournalPost;
        $this->posts->save($post, $validated);

        return Response::structured(['post' => $this->payloads->journalPost($post->fresh(), true)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Post title.')->required()->max(255),
            'slug' => $schema->string()->description('URL slug.')->max(255),
            'post_type' => $schema->string()->description('advice, venue, real_wedding, engagement, brand, or announcement.'),
            'excerpt' => $schema->string()->description('Excerpt.'),
            'body' => $schema->string()->description('Post body HTML.'),
            'hero_media_id' => $schema->integer()->description('Hero media id.')->min(1),
            'gallery_id' => $schema->integer()->description('Gallery id.')->min(1),
            'author_name' => $schema->string()->description('Author name.')->max(255),
            'seo_title' => $schema->string()->description('SEO title.')->max(255),
            'seo_description' => $schema->string()->description('SEO description.'),
            'canonical_url' => $schema->string()->description('Canonical URL.')->max(255),
            'category_ids' => $schema->array()->description('Category ids.'),
            'tag_ids' => $schema->array()->description('Tag ids.'),
            'venue_ids' => $schema->array()->description('Venue ids.'),
        ];
    }
}
