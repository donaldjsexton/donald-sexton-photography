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

class CreateDraftStoryTool extends Tool
{
    use RegistersForAbility;

    protected string $name = 'create_draft_story';

    protected string $description = 'Create a wedding story draft. The story stays unpublished even if a status is supplied. Publishing is a separate tool.';

    public function __construct(
        private readonly CmsMcpGate $gate,
        private readonly DraftStoryService $stories,
    ) {}

    public function shouldRegister(): bool
    {
        return $this->credentialAllows(CmsMcpCredential::DRAFT);
    }

    public function handle(Request $request): ResponseFactory
    {
        $this->gate->authorize(CmsMcpCredential::DRAFT);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
        ]);

        return Response::structured([
            'story' => $this->stories->create($validated),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Story title.')->required()->max(255),
            'slug' => $schema->string()->description('Optional URL slug.')->max(255),
            'excerpt' => $schema->string()->description('Short excerpt shown when the body is empty.'),
            'body' => $schema->string()->description('Full story. Plain paragraphs or existing HTML such as p, strong, and em.'),
            'seo_title' => $schema->string()->description('Optional SEO title.')->max(255),
            'seo_description' => $schema->string()->description('Optional SEO description.'),
        ];
    }
}
