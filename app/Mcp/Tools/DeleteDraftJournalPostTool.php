<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Models\CmsMcpCredential;
use App\Models\JournalPost;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class DeleteDraftJournalPostTool extends CmsTool
{
    protected string $name = 'delete_draft_journal_post';

    protected string $description = 'Permanently delete a draft journal post. Refuses a post that is not a draft, so published posts are never removed.';

    public function __construct(CmsMcpGate $gate)
    {
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

        $id = $post->id;
        $post->delete();

        return Response::structured(['deleted' => true, 'post_id' => $id]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->description('Draft post id.')->required()->min(1),
        ];
    }
}
