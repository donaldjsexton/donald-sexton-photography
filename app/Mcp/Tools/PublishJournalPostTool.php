<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\JournalPost;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class PublishJournalPostTool extends CmsTool
{
    protected string $name = 'publish_journal_post';

    protected string $description = 'Publish a draft journal post. This is the only journal tool that makes a post public. Repeating it changes nothing else.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly CmsPayloads $payloads,
    ) {
        parent::__construct($gate);
    }

    protected function ability(): string
    {
        return CmsMcpCredential::JOURNAL_PUBLISH;
    }

    protected function run(Request $request): ResponseFactory
    {
        $validated = $this->validated($request, ['post_id' => ['required', 'integer', 'min:1']]);
        $post = JournalPost::query()->find((int) $validated['post_id']);

        if (! $post instanceof JournalPost) {
            $this->notFound('post');
        }

        if ($post->status !== 'published') {
            $this->mustBeDraft('post', $post->status);

            $post->status = 'published';

            if ($post->published_at === null || $post->published_at->isFuture()) {
                $post->published_at = now();
            }

            $post->save();
        }

        return Response::structured(['post' => $this->payloads->journalPost($post->fresh(), true)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->description('Draft post id to publish.')->required()->min(1),
        ];
    }
}
