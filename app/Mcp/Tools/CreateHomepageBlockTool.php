<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\HomepageSetting;
use App\Services\Cms\BlockManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class CreateHomepageBlockTool extends CmsTool
{
    protected string $name = 'create_homepage_block';

    protected string $description = 'Add a block to the homepage. Homepage blocks are live as soon as they are visible.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly BlockManager $blocks,
        private readonly CmsPayloads $payloads,
    ) {
        parent::__construct($gate);
    }

    protected function ability(): string
    {
        return CmsMcpCredential::SETTINGS_WRITE;
    }

    protected function run(Request $request): ResponseFactory
    {
        $owner = HomepageSetting::query()->firstOrCreate([]);
        $block = $this->blocks->create($owner, $this->validated($request, $this->blocks->rules()));

        return Response::structured(['block' => $this->payloads->block($block->fresh())]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('Block type key from the block library.')->required(),
            'heading' => $schema->string()->description('Heading.')->max(255),
            'subheading' => $schema->string()->description('Subheading.')->max(255),
            'body' => $schema->string()->description('Body.'),
            'data' => $schema->object()->description('Type-specific block data.'),
            'is_visible' => $schema->boolean()->description('Whether the block shows. Defaults to true.'),
            'sort_order' => $schema->integer()->description('Position. Defaults to the end.')->min(0),
        ];
    }
}
