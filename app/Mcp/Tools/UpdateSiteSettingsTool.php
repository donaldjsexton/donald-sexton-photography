<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Models\CmsMcpCredential;
use App\Services\Cms\SiteSettingsManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class UpdateSiteSettingsTool extends CmsTool
{
    protected string $name = 'update_site_settings';

    protected string $description = 'Change allowlisted site settings: analytics id, social links, search verification tags, and business details. Only the fields you send change, and changes are live. OAuth tokens, Square credentials, the IndexNow key, and Business Profile links cannot be changed here.';

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
        $rules = Arr::only($this->settings->rules(), SiteSettingsManager::MCP_FIELDS);
        $input = Arr::only($request->all(), SiteSettingsManager::MCP_FIELDS);

        if ($input === []) {
            throw ValidationException::withMessages(['settings' => 'Send at least one editable setting.']);
        }

        $validated = Validator::make($input, $rules, $this->settings->messages())->validate();
        $this->settings->apply($this->settings->current(), $validated);

        return Response::structured([
            'updated' => array_keys($validated),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'google_analytics_measurement_id' => $schema->string()->description('GA4 id like G-ABC123XYZ.')->max(32),
            'instagram_url' => $schema->string()->description('Full URL.')->max(255),
            'pinterest_url' => $schema->string()->description('Full URL.')->max(255),
            'facebook_url' => $schema->string()->description('Full URL.')->max(255),
            'youtube_url' => $schema->string()->description('Full URL.')->max(255),
            'tiktok_url' => $schema->string()->description('Full URL.')->max(255),
            'x_url' => $schema->string()->description('Full URL.')->max(255),
            'google_site_verification' => $schema->string()->description('Verification token.')->max(255),
            'bing_site_verification' => $schema->string()->description('Verification token.')->max(255),
            'pinterest_site_verification' => $schema->string()->description('Verification token.')->max(255),
            'business_phone' => $schema->string()->description('Business phone.')->max(32),
            'business_email' => $schema->string()->description('Business email.')->max(255),
            'business_street_address' => $schema->string()->description('Street address.')->max(255),
            'business_locality' => $schema->string()->description('City.')->max(128),
            'business_region' => $schema->string()->description('State or region.')->max(64),
            'business_postal_code' => $schema->string()->description('Postal code.')->max(32),
            'business_country' => $schema->string()->description('Two letter country code.'),
            'business_latitude' => $schema->number()->description('Latitude.'),
            'business_longitude' => $schema->number()->description('Longitude.'),
            'business_hours_note' => $schema->string()->description('Hours note.')->max(255),
            'business_price_range' => $schema->string()->description('Price range such as $$$.')->max(16),
        ];
    }
}
