<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Models\CmsMcpCredential;
use App\Models\Page;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class DeleteDraftPageTool extends CmsTool
{
    protected string $name = 'delete_draft_page';

    protected string $description = 'Permanently delete a draft page. Refuses a page that is not a draft, so published pages are never removed.';

    public function __construct(CmsMcpGate $gate)
    {
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

        $id = $page->id;
        $page->delete();

        return Response::structured(['deleted' => true, 'page_id' => $id]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page_id' => $schema->integer()->description('Draft page id.')->required()->min(1),
        ];
    }
}
