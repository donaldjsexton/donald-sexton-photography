<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetInvoiceTool extends CmsTool
{
    protected string $name = 'get_invoice';

    protected string $description = 'Read one invoice with line items and installment schedule. Read only. Returns no payment records, payment tokens, public portal ids, or internal notes.';

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
        $validated = $this->validated($request, ['invoice_id' => ['required', 'integer', 'min:1']]);
        $invoice = Invoice::query()->find((int) $validated['invoice_id']);

        if (! $invoice instanceof Invoice) {
            $this->notFound('invoice');
        }

        return Response::structured(['invoice' => $this->payloads->invoice($invoice, true)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->description('Invoice id.')->required()->min(1),
        ];
    }
}
