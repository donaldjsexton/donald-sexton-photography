<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Models\CmsMcpCredential;
use App\Services\Cms\SiteSettingsManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetSiteSettingsTool extends CmsTool
{
    protected string $name = 'get_site_settings';

    protected string $description = 'Read the editable site settings: analytics id, social links, search verification tags, and business details. Secrets such as OAuth tokens, Square credentials, and the IndexNow key are never returned; connection state is reported as true or false only.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly SiteSettingsManager $settings,
    ) {
        parent::__construct($gate);
    }

    protected function ability(): string
    {
        return CmsMcpCredential::SETTINGS_WRITE;
    }

    protected function run(Request $request): ResponseFactory
    {
        $settings = $this->settings->current();

        return Response::structured([
            'settings' => collect(SiteSettingsManager::MCP_FIELDS)
                ->mapWithKeys(fn (string $field): array => [$field => $settings->{$field}])
                ->all(),
            'connections' => [
                'google_connected' => $settings->googleIsConnected(),
                'square_connected' => $settings->squareIsConnected(),
                'indexnow_key_set' => filled($settings->indexnow_key),
            ],
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
