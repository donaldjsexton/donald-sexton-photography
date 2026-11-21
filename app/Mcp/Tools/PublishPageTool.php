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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class PublishPageTool extends CmsTool
{
    protected string $name = 'publish_page';

    protected string $description = 'Publish a draft page. This is the only page tool that makes a page public. Repeating it changes nothing else.';

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

        if ($page->status !== 'published') {
            $this->mustBeDraft('page', $page->status);

            $page->status = 'published';

            if ($page->published_at === null || $page->published_at->isFuture()) {
                $page->published_at = now();
            }

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
            'page_id' => $schema->integer()->description('Draft page id to publish.')->required()->min(1),
        ];
    }
}
