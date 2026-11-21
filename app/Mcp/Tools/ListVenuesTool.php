<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\Venue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListVenuesTool extends CmsTool
{
    protected string $name = 'list_venues';

    protected string $description = 'List venues with city, region, and featured flag. Read only.';

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
        $validated = $this->validated($request, [...$this->listRules(), 'search' => ['sometimes', 'string', 'max:100']]);

        $query = Venue::query()
            ->when(filled($validated['search'] ?? null), function ($query) use ($validated): void {
                $like = '%'.$validated['search'].'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('city', 'like', $like)->orWhere('region', 'like', $like));
            })
            ->orderByDesc('is_featured')
            ->orderBy('name');

        return $this->listing($query, $validated, fn (Venue $venue): array => $this->payloads->venue($venue), 'venues');
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Page size, 1 to 50. Defaults to 25.')->min(1)->max(50),
            'page' => $schema->integer()->description('Page number, starting at 1.')->min(1),
            'search' => $schema->string()->description('Match on name, city, or region.')->max(100),
        ];
    }
}
