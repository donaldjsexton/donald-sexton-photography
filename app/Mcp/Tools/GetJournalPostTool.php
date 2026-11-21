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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetJournalPostTool extends CmsTool
{
    protected string $name = 'get_journal_post';

    protected string $description = 'Read one journal post by id or slug, including body and relation ids. Read only.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly CmsPayloads $payloads,
    ) {
        parent::__construct($gate);
    }

    protected function ability(): string
    {
        return CmsMcpCredential::SITE_READ;
    }

    protected function run(Request $request): ResponseFactory
    {
        $validated = $this->validated($request, [
            'post_id' => ['nullable', 'integer', 'required_without:slug'],
            'slug' => ['nullable', 'string', 'max:255', 'required_without:post_id'],
        ]);

        $post = isset($validated['post_id'])
            ? JournalPost::query()->find((int) $validated['post_id'])
            : JournalPost::query()->where('slug', $validated['slug'])->first();

        if (! $post instanceof JournalPost) {
            $this->notFound('post');
        }

        return Response::structured(['post' => $this->payloads->journalPost($post, true)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->description('Post id. Provide this or slug.')->min(1),
            'slug' => $schema->string()->description('Post slug. Provide this or post_id.')->max(255),
        ];
    }
}
