<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\Contract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetContractTool extends CmsTool
{
    protected string $name = 'get_contract';

    protected string $description = 'Read one contract including its body text. Read only. Returns no signer IP, user agent, public portal ids, or internal notes.';

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
        $validated = $this->validated($request, ['contract_id' => ['required', 'integer', 'min:1']]);
        $contract = Contract::query()->find((int) $validated['contract_id']);

        if (! $contract instanceof Contract) {
            $this->notFound('contract');
        }

        return Response::structured(['contract' => $this->payloads->contract($contract, true)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'contract_id' => $schema->integer()->description('Contract id.')->required()->min(1),
        ];
    }
}
