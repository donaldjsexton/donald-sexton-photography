<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\Inquiry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetInquiryTool extends CmsTool
{
    protected string $name = 'get_inquiry';

    protected string $description = 'Read one inquiry by id: name, email, status, event details, and dates. Phone numbers, message text, consent records, and tracking fields are not returned. Read only.';

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
        $validated = $this->validated($request, ['inquiry_id' => ['required', 'integer', 'min:1']]);
        $inquiry = Inquiry::query()->find((int) $validated['inquiry_id']);

        if (! $inquiry instanceof Inquiry) {
            $this->notFound('inquiry');
        }

        return Response::structured(['inquiry' => $this->payloads->inquiry($inquiry)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'inquiry_id' => $schema->integer()->description('Inquiry id.')->required()->min(1),
        ];
    }
}
