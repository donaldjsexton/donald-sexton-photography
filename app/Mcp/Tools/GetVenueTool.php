<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\Venue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetVenueTool extends CmsTool
{
    protected string $name = 'get_venue';

    protected string $description = 'Read one venue by id or slug. Returns public venue fields only; billing, referral, and portal fields are never returned. Read only.';

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
            'venue_id' => ['nullable', 'integer', 'required_without:slug'],
            'slug' => ['nullable', 'string', 'max:255', 'required_without:venue_id'],
        ]);

        $venue = isset($validated['venue_id'])
            ? Venue::query()->find((int) $validated['venue_id'])
            : Venue::query()->where('slug', $validated['slug'])->first();

        if (! $venue instanceof Venue) {
            $this->notFound('venue');
        }

        return Response::structured(['venue' => $this->payloads->venue($venue, true)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'venue_id' => $schema->integer()->description('Venue id. Provide this or slug.')->min(1),
            'slug' => $schema->string()->description('Venue slug. Provide this or venue_id.')->max(255),
        ];
    }
}
