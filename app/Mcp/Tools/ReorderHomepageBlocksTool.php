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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class ReorderHomepageBlocksTool extends CmsTool
{
    protected string $name = 'reorder_homepage_blocks';

    protected string $description = 'Set the order of homepage blocks. Ids that do not belong to the homepage are ignored. Changes are live.';

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
        $validated = $this->validated($request, [
            'block_ids' => ['required', 'array', 'max:200'],
            'block_ids.*' => ['integer'],
        ]);

        $owner = HomepageSetting::query()->firstOrCreate([]);
        $this->blocks->reorder($owner, $validated['block_ids']);

        return Response::structured(['homepage' => $this->payloads->homepage($owner->fresh())]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'block_ids' => $schema->array()->description('Block ids in the new order.')->required(),
        ];
    }
}
