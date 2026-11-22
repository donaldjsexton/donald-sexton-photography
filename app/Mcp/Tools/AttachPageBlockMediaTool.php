<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\Block;
use App\Models\CmsMcpCredential;
use App\Models\Media;
use App\Models\Page;
use App\Services\Cms\BlockManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class AttachPageBlockMediaTool extends CmsTool
{
    protected string $name = 'attach_page_block_media';

    protected string $description = 'Attach existing media to a block on a draft page, in the order given, so galleries and image blocks show photos. Media already on the block is left alone.';

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

        $validated = $this->validated($request, [
            'media_ids' => ['required', 'array', 'min:1', 'max:50'],
            'media_ids.*' => ['integer', 'exists:media,id'],
        ]);

        foreach (array_unique(array_map('intval', $validated['media_ids'])) as $mediaId) {
            if (! Media::query()->whereKey($mediaId)->exists() || $block->media()->whereKey($mediaId)->exists()) {
                continue;
            }

            $block->media()->attach($mediaId, [
                'role' => 'gallery',
                'sort_order' => $block->media()->count() === 0 ? 0 : (int) $block->media()->max('mediables.sort_order') + 1,
            ]);
        }

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
            'media_ids' => $schema->array()->items($schema->integer())->description('Media ids, in display order.')->required(),
        ];
    }
}
