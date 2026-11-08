<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\DraftStoryService;
use App\Mcp\Tools\Concerns\RegistersForAbility;
use App\Models\CmsMcpCredential;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class LookupStoryTool extends Tool
{
    use RegistersForAbility;

    protected string $name = 'lookup_story';

    protected string $description = 'Read one wedding story, including its saved body, SEO fields, hero alt text, and gallery order. Does not publish or change anything.';

    public function __construct(
        private readonly CmsMcpGate $gate,
        private readonly DraftStoryService $stories,
    ) {}

    public function shouldRegister(): bool
    {
        return $this->credentialAllows(CmsMcpCredential::READ);
    }

    public function handle(Request $request): ResponseFactory
    {
        $this->gate->authorize(CmsMcpCredential::READ);

        $validated = $request->validate([
            'story_id' => ['nullable', 'integer', 'required_without:slug'],
            'slug' => ['nullable', 'string', 'max:255', 'required_without:story_id'],
        ]);

        return $this->storyResponse($this->stories->lookup(
            isset($validated['story_id']) ? (int) $validated['story_id'] : null,
            $validated['slug'] ?? null,
        ));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'story_id' => $schema->integer()->description('Story id. Provide this or slug.')->min(1),
            'slug' => $schema->string()->description('Story slug. Provide this or story_id.')->max(255),
        ];
    }

    /**
     * @param  array<string, mixed>  $story
     */
    private function storyResponse(array $story): ResponseFactory
    {
        return Response::structured([
            'story' => $story,
        ]);
    }
}
