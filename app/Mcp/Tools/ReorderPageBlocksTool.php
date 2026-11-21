<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\Page;
use App\Services\Cms\BlockManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class ReorderPageBlocksTool extends CmsTool
{
    protected string $name = 'reorder_page_blocks';

    protected string $description = 'Set the order of blocks on a draft page. Ids that do not belong to the page are ignored.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly BlockManager $blocks,
        private readonly CmsPayloads $payloads,
    ) {
        parent::__construct($gate);
    }

    protected function ability(): string
    {
        return CmsMcpCredential::PAGES_WRITE;
    }

    protected function run(Request $request): ResponseFactory
    {
        $validated = $this->validated($request, [
            'page_id' => ['required', 'integer', 'min:1'],
            'block_ids' => ['required', 'array', 'max:200'],
            'block_ids.*' => ['integer'],
        ]);

        $page = Page::query()->find((int) $validated['page_id']);

        if (! $page instanceof Page) {
            $this->notFound('page');
        }

        $this->mustBeDraft('page', $page->status);

        $this->blocks->reorder($page, $validated['block_ids']);

        return Response::structured(['page' => $this->payloads->page($page->fresh(), true)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page_id' => $schema->integer()->description('Draft page id.')->required()->min(1),
            'block_ids' => $schema->array()->description('Block ids in the new order.')->required(),
        ];
    }
}
