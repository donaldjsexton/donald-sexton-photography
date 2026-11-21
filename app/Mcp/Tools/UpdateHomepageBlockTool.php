<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\Block;
use App\Models\CmsMcpCredential;
use App\Models\HomepageSetting;
use App\Services\Cms\BlockManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class UpdateHomepageBlockTool extends CmsTool
{
    protected string $name = 'update_homepage_block';

    protected string $description = 'Replace the content of one homepage block. Set is_visible to false to hide it. Changes are live.';

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
        $block = Block::query()->find((int) $request->get('block_id'));

        if (! $block instanceof Block || ! $this->blocks->owns($owner, $block)) {
            $this->notFound('block');
        }

        $this->blocks->update($block, $this->validated($request, $this->blocks->rules()));

        return Response::structured(['block' => $this->payloads->block($block->fresh())]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'block_id' => $schema->integer()->description('Homepage block id.')->required()->min(1),
            'type' => $schema->string()->description('Block type key from the block library.')->required(),
            'heading' => $schema->string()->description('Heading.')->max(255),
            'subheading' => $schema->string()->description('Subheading.')->max(255),
            'body' => $schema->string()->description('Body.'),
            'data' => $schema->object()->description('Type-specific block data.'),
            'is_visible' => $schema->boolean()->description('Whether the block shows.'),
            'sort_order' => $schema->integer()->description('Position.')->min(0),
        ];
    }
}
