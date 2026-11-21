<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\Contract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListProposalsTool extends CmsTool
{
    protected string $name = 'list_proposals';

    protected string $description = 'List proposals: contracts that have an invoice attached, each with its contract and invoice summary, as drafted on the admin proposal screen. Read only.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly CmsPayloads $payloads,
    ) {
        parent::__construct($gate);
    }

    protected function ability(): string
    {
        return CmsMcpCredential::FINANCE_READ;
    }

    protected function run(Request $request): ResponseFactory
    {
        $validated = $this->validated($request, [...$this->listRules(), 'status' => ['sometimes', 'string', Rule::in(array_keys(Contract::statusOptions()))]]);

        $query = Contract::query()
            ->with(['billable', 'invoice.billable'])
            ->whereNotNull('invoice_id')
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->orderByDesc('issue_date')
            ->orderByDesc('id');

        return $this->listing($query, $validated, fn (Contract $contract): array => $this->payloads->proposal($contract), 'proposals');
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Page size, 1 to 50. Defaults to 25.')->min(1)->max(50),
            'page' => $schema->integer()->description('Page number, starting at 1.')->min(1),
            'status' => $schema->string()->description('Contract status: draft, sent, signed, declined, or void.'),
        ];
    }
}
