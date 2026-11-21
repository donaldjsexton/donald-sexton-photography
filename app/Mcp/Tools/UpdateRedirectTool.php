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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class UpdateRedirectTool extends CmsTool
{
    protected string $name = 'update_redirect';

    protected string $description = 'Change an existing redirect. Only the fields you send change. Same path rules as create_redirect.';

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
        $redirect = Redirect::query()->find((int) $request->get('redirect_id'));

        if (! $redirect instanceof Redirect) {
            $this->notFound('redirect');
        }

        $this->redirects->save($redirect, $this->validated($request, $this->partial($this->redirects->rules($redirect))));

        return Response::structured(['redirect' => $this->payloads->redirect($redirect->fresh())]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'redirect_id' => $schema->integer()->description('Redirect id.')->required()->min(1),
            'from_path' => $schema->string()->description('Path to redirect.')->max(255),
            'to_path' => $schema->string()->description('Site path to send visitors to.')->max(255),
            'status_code' => $schema->integer()->description('301, 302, 307, or 308.'),
        ];
    }
}
