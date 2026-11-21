<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\Media;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListMediaTool extends CmsTool
{
    protected string $name = 'list_media';

    protected string $description = 'List media records with alt text and URL. Filter to images missing alt text. Read only.';

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
            ...$this->listRules(),
            'missing_alt' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:100'],
        ]);

        $query = Media::query()
            ->when((bool) ($validated['missing_alt'] ?? false), fn ($query) => $query->where(fn ($q) => $q->whereNull('alt_text')->orWhere('alt_text', '')))
            ->when(filled($validated['search'] ?? null), function ($query) use ($validated): void {
                $like = '%'.$validated['search'].'%';
                $query->where(fn ($q) => $q->where('filename', 'like', $like)->orWhere('alt_text', 'like', $like));
            })
            ->orderByDesc('id');

        return $this->listing($query, $validated, fn (Media $media): array => $this->payloads->media($media), 'media');
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Page size, 1 to 50. Defaults to 25.')->min(1)->max(50),
            'page' => $schema->integer()->description('Page number, starting at 1.')->min(1),
            'missing_alt' => $schema->boolean()->description('Only images without alt text.'),
            'search' => $schema->string()->description('Match on filename or alt text.')->max(100),
        ];
    }
}
