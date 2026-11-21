<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\Inquiry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListInquiriesTool extends CmsTool
{
    protected string $name = 'list_inquiries';

    protected string $description = 'List inquiries with name, email, status, event date, and venue. Archived inquiries are left out unless you filter by status archived. Phone numbers and message text are not returned. Read only.';

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
        $validated = $this->validated($request, [
            ...$this->listRules(),
            'status' => ['sometimes', 'string', Rule::in(array_keys(Inquiry::statusOptions()))],
            'search' => ['sometimes', 'string', 'max:100'],
        ]);

        $query = Inquiry::query()
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']), fn ($query) => $query->where('status', '!=', 'archived'))
            ->when(filled($validated['search'] ?? null), function ($query) use ($validated): void {
                $like = '%'.$validated['search'].'%';
                $query->where(fn ($q) => $q->where('primary_name', 'like', $like)->orWhere('partner_name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('venue_name', 'like', $like)->orWhere('location_city', 'like', $like));
            })
            ->adminOrdered();

        return $this->listing($query, $validated, fn (Inquiry $inquiry): array => $this->payloads->inquiry($inquiry), 'inquiries');
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Page size, 1 to 50. Defaults to 25.')->min(1)->max(50),
            'page' => $schema->integer()->description('Page number, starting at 1.')->min(1),
            'status' => $schema->string()->description('new, active, follow_up, booked, or archived.'),
            'search' => $schema->string()->description('Match on name, email, venue, or city.')->max(100),
        ];
    }
}
