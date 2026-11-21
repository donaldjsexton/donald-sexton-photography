<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\Page;
use App\Services\Cms\PageManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class CreateDraftPageTool extends CmsTool
{
    protected string $name = 'create_draft_page';

    protected string $description = 'Create a page as a draft. The status is always draft and the page is never published here.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly PageManager $pages,
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
        $rules = Arr::except($this->pages->rules(), ['status', 'published_at']);
        $validated = $this->validated($request, $rules, ['template' => 'custom']);
        $validated['status'] = 'draft';
        $validated['published_at'] = null;

        $page = new Page;
        $this->pages->save($page, $validated);

        return Response::structured(['page' => $this->payloads->page($page->fresh(), true)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Page title.')->required()->max(255),
            'slug' => $schema->string()->description('Optional URL slug. Defaults to the title.')->max(255),
            'template' => $schema->string()->description('home, about, collections, faq, location, or custom. Defaults to custom.'),
            'excerpt' => $schema->string()->description('Short excerpt.'),
            'body' => $schema->string()->description('Page body HTML.'),
            'hero_media_id' => $schema->integer()->description('Hero media id.')->min(1),
            'seo_title' => $schema->string()->description('SEO title.')->max(255),
            'seo_description' => $schema->string()->description('SEO description.'),
            'canonical_url' => $schema->string()->description('Canonical URL.')->max(255),
            'sort_order' => $schema->integer()->description('Sort order.')->min(0),
            'faqs_text' => $schema->string()->description('One FAQ per line as Question | Answer.')->max(8000),
        ];
    }
}
