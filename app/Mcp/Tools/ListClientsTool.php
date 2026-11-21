<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\Client;
use App\Models\CmsMcpCredential;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListClientsTool extends CmsTool
{
    protected string $name = 'list_clients';

    protected string $description = 'List clients with name, email, and inquiry and invoice counts. Phone numbers, addresses, notes, and portal data are not returned. Read only.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly CmsPayloads $payloads,
    ) {
        parent::__construct($gate);
    }

    protected function ability(): string
    {
        return CmsMcpCredential::INQUIRIES_READ;
    }

    protected function run(Request $request): ResponseFactory
    {
        $validated = $this->validated($request, [...$this->listRules(), 'search' => ['sometimes', 'string', 'max:100']]);

        $query = Client::query()
            ->withCount(['inquiries', 'invoices'])
            ->search($validated['search'] ?? null)
            ->orderBy('last_name')
            ->orderBy('first_name');

        return $this->listing($query, $validated, fn (Client $client): array => $this->payloads->client($client), 'clients');
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Page size, 1 to 50. Defaults to 25.')->min(1)->max(50),
            'page' => $schema->integer()->description('Page number, starting at 1.')->min(1),
            'search' => $schema->string()->description('Match on name, email, or company.')->max(100),
        ];
    }
}
