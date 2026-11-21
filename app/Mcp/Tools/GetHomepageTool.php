<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\HomepageSetting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetHomepageTool extends CmsTool
{
    protected string $name = 'get_homepage';

    protected string $description = 'Read the homepage settings and all homepage blocks in order. Read only.';

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
        return Response::structured([
            'homepage' => $this->payloads->homepage(HomepageSetting::query()->first() ?? new HomepageSetting),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
        ];
    }
}
