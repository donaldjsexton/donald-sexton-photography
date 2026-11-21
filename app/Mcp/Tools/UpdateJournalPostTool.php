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

class UpdateJournalPostTool extends CmsTool
{
    protected string $name = 'update_journal_post';

    protected string $description = 'Update a draft journal post. Only the fields you send change. Refuses a post that is not a draft and never publishes.';

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
        $post = JournalPost::query()->find((int) $request->get('post_id'));

        if (! $post instanceof JournalPost) {
            $this->notFound('post');
        }

        $this->mustBeDraft('post', $post->status);

        $rules = $this->partial(Arr::except($this->posts->rules($post), ['status', 'published_at', 'original_wp_post_id', 'original_wp_url']));
        $this->posts->save($post, $this->validated($request, $rules), partial: true);

        return Response::structured(['post' => $this->payloads->journalPost($post->fresh(), true)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->description('Draft post id.')->required()->min(1),
            'title' => $schema->string()->description('Post title.')->max(255),
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
