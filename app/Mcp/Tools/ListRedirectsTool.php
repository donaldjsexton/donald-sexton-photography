<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\Redirect;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListRedirectsTool extends CmsTool
{
    protected string $name = 'list_redirects';

    protected string $description = 'List redirects. Read only.';

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

        $query = Redirect::query()
            ->when(filled($validated['search'] ?? null), function ($query) use ($validated): void {
                $like = '%'.$validated['search'].'%';
                $query->where(fn ($q) => $q->where('from_path', 'like', $like)->orWhere('to_path', 'like', $like));
            })
            ->orderByDesc('id');

        return $this->listing($query, $validated, fn (Redirect $redirect): array => $this->payloads->redirect($redirect), 'redirects');
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Page size, 1 to 50. Defaults to 25.')->min(1)->max(50),
            'page' => $schema->integer()->description('Page number, starting at 1.')->min(1),
            'search' => $schema->string()->description('Match on either path.')->max(100),
        ];
    }
}
