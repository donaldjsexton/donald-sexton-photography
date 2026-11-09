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

class UpdateDraftStoryTool extends Tool
{
    use RegistersForAbility;

    protected string $name = 'update_draft_story';

    protected string $description = 'Update a draft story title, body, SEO fields, descriptive image alt text, ordered gallery, and selected hero. Refuses published stories and never publishes.';

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
            'story_id' => ['required', 'integer', 'min:1'],
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'excerpt' => ['sometimes', 'nullable', 'string'],
            'body' => ['sometimes', 'nullable', 'string'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string'],
            'hero_media_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'gallery' => ['sometimes', 'array', 'max:60'],
            'gallery.*.media_id' => ['required', 'integer', 'min:1'],
            'gallery.*.alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        return Response::structured([
            'story' => $this->stories->update($validated),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'story_id' => $schema->integer()->description('Draft story id.')->required()->min(1),
            'title' => $schema->string()->description('Replacement title.')->max(255),
            'slug' => $schema->string()->description('Replacement slug.')->max(255),
            'excerpt' => $schema->string()->description('Replacement excerpt.'),
            'body' => $schema->string()->description('Replacement full story body.'),
            'seo_title' => $schema->string()->description('Replacement SEO title.')->max(255),
            'seo_description' => $schema->string()->description('Replacement SEO description.'),
            'hero_media_id' => $schema->integer()->description('Hero media id. It must already be in the ordered gallery.')->min(1),
            'gallery' => $schema->array()->description('Ordered gallery. The first item is sort order 0. Include alt_text only when setting that image alt.')->max(60),
        ];
    }
}
