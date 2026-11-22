<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\Page;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class UnpublishPageTool extends CmsTool
{
    protected string $name = 'unpublish_page';

    protected string $description = 'Return a published page to draft so it can be edited, then publish it again. Nothing is deleted. Repeating it changes nothing else.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly CmsPayloads $payloads,
    ) {
        parent::__construct($gate);
    }

    protected function ability(): string
    {
        return CmsMcpCredential::PAGES_PUBLISH;
    }

    protected function run(Request $request): ResponseFactory
    {
        $validated = $this->validated($request, ['page_id' => ['required', 'integer', 'min:1']]);
        $page = Page::query()->find((int) $validated['page_id']);

        if (! $page instanceof Page) {
            $this->notFound('page');
        }

        if ($page->status !== 'draft') {
            $page->status = 'draft';
            $page->save();
        }

        return Response::structured(['page' => $this->payloads->page($page->fresh(), true)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page_id' => $schema->integer()->description('Published page id to return to draft.')->required()->min(1),
        ];
    }
}
