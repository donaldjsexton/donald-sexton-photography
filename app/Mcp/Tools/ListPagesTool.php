<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\Page;
use App\Services\Cms\PageManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListPagesTool extends CmsTool
{
    protected string $name = 'list_pages';

    protected string $description = 'List standalone pages with status, template, and SEO fields. Read only.';

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
        $validated = $this->validated($request, [...$this->listRules(), 'status' => ['sometimes', 'string', Rule::in(PageManager::statuses())]]);

        $query = Page::query()
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->orderBy('sort_order')
            ->orderBy('id');

        return $this->listing($query, $validated, fn (Page $page): array => $this->payloads->page($page), 'pages');
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Page size, 1 to 50. Defaults to 25.')->min(1)->max(50),
            'page' => $schema->integer()->description('Page number, starting at 1.')->min(1),
            'status' => $schema->string()->description('Filter by draft, published, or archived.'),
        ];
    }
}
