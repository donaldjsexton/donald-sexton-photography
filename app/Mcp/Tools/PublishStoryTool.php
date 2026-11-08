<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\DraftStoryService;
use App\Models\CmsMcpCredential;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class PublishStoryTool extends Tool
{
    protected string $name = 'publish_story';

    protected string $description = 'Publish a draft wedding story. This is the only operation that makes a story public. Repeating it does not change the saved body, gallery, hero, or alt text.';

    public function __construct(
        private readonly CmsMcpGate $gate,
        private readonly DraftStoryService $stories,
    ) {}

    public function handle(Request $request): ResponseFactory
    {
        $this->gate->authorize(CmsMcpCredential::PUBLISH);

        $validated = $request->validate([
            'story_id' => ['required', 'integer', 'min:1'],
        ]);

        return Response::structured([
            'story' => $this->stories->publish((int) $validated['story_id']),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'story_id' => $schema->integer()->description('Draft story id to publish.')->required()->min(1),
        ];
    }
}
