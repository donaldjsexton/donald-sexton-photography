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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetPageTool extends CmsTool
{
    protected string $name = 'get_page';

    protected string $description = 'Read one page by id or slug, including body, FAQs, attached media, and all page blocks in order. Read only.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly CmsPayloads $payloads,
    ) {
        parent::__construct($gate);
    }

    protected function ability(): string
    {
        return CmsMcpCredential::SITE_READ;
    }

    protected function run(Request $request): ResponseFactory
    {
        $validated = $this->validated($request, [
            'page_id' => ['nullable', 'integer', 'required_without:slug'],
            'slug' => ['nullable', 'string', 'max:255', 'required_without:page_id'],
        ]);

        $page = isset($validated['page_id'])
            ? Page::query()->find((int) $validated['page_id'])
            : Page::query()->where('slug', $validated['slug'])->first();

        if (! $page instanceof Page) {
            $this->notFound('page');
        }

        return Response::structured(['page' => $this->payloads->page($page, true)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page_id' => $schema->integer()->description('Page id. Provide this or slug.')->min(1),
            'slug' => $schema->string()->description('Page slug. Provide this or page_id.')->max(255),
        ];
    }
}
