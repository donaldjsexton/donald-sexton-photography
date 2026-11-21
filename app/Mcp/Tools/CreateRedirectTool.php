<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\Redirect;
use App\Services\Cms\RedirectManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class CreateRedirectTool extends CmsTool
{
    protected string $name = 'create_redirect';

    protected string $description = 'Create a redirect from one site path to another. Both paths must start with a single slash; external targets are refused. Redirects are live immediately.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly RedirectManager $redirects,
        private readonly CmsPayloads $payloads,
    ) {
        parent::__construct($gate);
    }

    protected function ability(): string
    {
        return CmsMcpCredential::REDIRECTS_WRITE;
    }

    protected function run(Request $request): ResponseFactory
    {
        $validated = $this->validated($request, $this->redirects->rules());

        $redirect = new Redirect;
        $this->redirects->save($redirect, $validated);

        return Response::structured(['redirect' => $this->payloads->redirect($redirect->fresh())]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from_path' => $schema->string()->description('Path to redirect, such as /old-page.')->required()->max(255),
            'to_path' => $schema->string()->description('Site path to send visitors to.')->required()->max(255),
            'status_code' => $schema->integer()->description('301, 302, 307, or 308. Defaults to 301.'),
        ];
    }
}
