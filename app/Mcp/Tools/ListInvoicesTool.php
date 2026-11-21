<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListInvoicesTool extends CmsTool
{
    protected string $name = 'list_invoices';

    protected string $description = 'List invoices with status, dates, and totals. Read only. Returns no payment records, payment tokens, public portal ids, or internal notes.';

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
        $validated = $this->validated($request, [...$this->listRules(), 'status' => ['sometimes', 'string', Rule::in(array_keys(Invoice::statusOptions()))]]);

        $query = Invoice::query()
            ->with('billable')
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->orderByDesc('issue_date')
            ->orderByDesc('id');

        return $this->listing($query, $validated, fn (Invoice $invoice): array => $this->payloads->invoice($invoice), 'invoices');
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Page size, 1 to 50. Defaults to 25.')->min(1)->max(50),
            'page' => $schema->integer()->description('Page number, starting at 1.')->min(1),
            'status' => $schema->string()->description('draft, sent, partially_paid, paid, overdue, void, or refunded.'),
        ];
    }
}
