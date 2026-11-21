<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\Block;
use App\Models\CmsMcpCredential;
use App\Models\Page;
use App\Services\Cms\BlockManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class UpdatePageBlockTool extends CmsTool
{
    protected string $name = 'update_page_block';

    protected string $description = 'Replace the content of one block on a draft page. Refuses a page that is not a draft.';

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
        $page = Page::query()->find((int) $request->get('page_id'));

        if (! $page instanceof Page) {
            $this->notFound('page');
        }

        $this->mustBeDraft('page', $page->status);

        $block = Block::query()->find((int) $request->get('block_id'));

        if (! $block instanceof Block || ! $this->blocks->owns($page, $block)) {
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
            'page_id' => $schema->integer()->description('Draft page id.')->required()->min(1),
            'block_id' => $schema->integer()->description('Block id on that page.')->required()->min(1),
            'type' => $schema->string()->description('Block type key from the block library.')->required(),
            'heading' => $schema->string()->description('Heading.')->max(255),
            'subheading' => $schema->string()->description('Subheading.')->max(255),
            'body' => $schema->string()->description('Body.'),
            'data' => $schema->object()->description('Type-specific block data.'),
            'is_visible' => $schema->boolean()->description('Whether the block shows on the page.'),
            'sort_order' => $schema->integer()->description('Position. Defaults to the end.')->min(0),
        ];
    }
}
