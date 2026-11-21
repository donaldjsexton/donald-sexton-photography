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

class UpdatePageTool extends CmsTool
{
    protected string $name = 'update_page';

    protected string $description = 'Update a draft page. Only the fields you send change. Refuses a page that is not a draft and never publishes.';

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
        $page = Page::query()->find((int) $request->get('page_id'));

        if (! $page instanceof Page) {
            $this->notFound('page');
        }

        $this->mustBeDraft('page', $page->status);

        $rules = $this->partial(Arr::except($this->pages->rules($page), ['status', 'published_at']));
        $validated = $this->validated($request, $rules);
        $this->pages->save($page, $validated, partial: true);

        return Response::structured(['page' => $this->payloads->page($page->fresh(), true)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page_id' => $schema->integer()->description('Draft page id.')->required()->min(1),
            'title' => $schema->string()->description('Replacement title.')->max(255),
            'slug' => $schema->string()->description('Replacement slug.')->max(255),
            'template' => $schema->string()->description('home, about, collections, faq, location, or custom.'),
            'excerpt' => $schema->string()->description('Replacement excerpt.'),
            'body' => $schema->string()->description('Replacement body HTML.'),
            'hero_media_id' => $schema->integer()->description('Hero media id.')->min(1),
            'seo_title' => $schema->string()->description('SEO title.')->max(255),
            'seo_description' => $schema->string()->description('SEO description.'),
            'canonical_url' => $schema->string()->description('Canonical URL.')->max(255),
            'sort_order' => $schema->integer()->description('Sort order.')->min(0),
            'faqs_text' => $schema->string()->description('One FAQ per line as Question | Answer.')->max(8000),
        ];
    }
}
