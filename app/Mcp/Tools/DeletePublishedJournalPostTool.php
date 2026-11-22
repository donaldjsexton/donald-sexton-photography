<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Models\CmsMcpCredential;
use App\Models\JournalPost;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class DeletePublishedJournalPostTool extends CmsTool
{
    protected string $name = 'delete_published_journal_post';

    protected string $description = 'Permanently delete a published journal post, for example a duplicate. Requires the post slug as confirm_slug. Add a redirect first if the URL has traffic.';

    public function __construct(CmsMcpGate $gate)
    {
        parent::__construct($gate);
    }

    protected function ability(): string
    {
        return CmsMcpCredential::JOURNAL_PUBLISH;
    }

    protected function run(Request $request): ResponseFactory
    {
        $post = JournalPost::query()->find((int) $request->get('post_id'));

        if (! $post instanceof JournalPost) {
            $this->notFound('post');
        }

        if ($post->status !== 'published') {
            throw ValidationException::withMessages([
                'post' => 'Only a published post can be deleted here. Use delete_draft_journal_post for drafts.',
            ]);
        }

        if ((string) $request->get('confirm_slug') !== $post->slug) {
            throw ValidationException::withMessages([
                'confirm_slug' => 'confirm_slug must exactly match the post slug.',
            ]);
        }

        $id = $post->id;
        $slug = $post->slug;
        $post->delete();

        return Response::structured(['deleted' => true, 'post_id' => $id, 'slug' => $slug]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->description('Published post id.')->required()->min(1),
            'confirm_slug' => $schema->string()->description('The post slug, to confirm the right post.')->required()->max(255),
        ];
    }
}
