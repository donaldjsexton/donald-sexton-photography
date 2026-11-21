<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Services\Cms\HomepageSettingsManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class UpdateHomepageSettingsTool extends CmsTool
{
    protected string $name = 'update_homepage_settings';

    protected string $description = 'Change homepage hero and closing text and featured content ids. Only the fields you send change, and changes are live.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly HomepageSettingsManager $settings,
        private readonly CmsPayloads $payloads,
    ) {
        parent::__construct($gate);
    }

    protected function ability(): string
    {
        return CmsMcpCredential::SETTINGS_WRITE;
    }

    protected function run(Request $request): ResponseFactory
    {
        $rules = $this->partial($this->settings->rules());
        $validated = $this->validated($request, $rules);

        if ($validated === []) {
            throw ValidationException::withMessages(['homepage' => 'Send at least one homepage field.']);
        }

        $saved = $this->settings->save($validated);

        return Response::structured(['homepage' => $this->payloads->homepage($saved->fresh())]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'hero_heading' => $schema->string()->description('Hero heading.')->max(255),
            'hero_subheading' => $schema->string()->description('Hero subheading.'),
            'hero_media_id' => $schema->integer()->description('Hero media id.')->min(1),
            'featured_story_ids_json' => $schema->array()->description('Featured story ids in order.'),
            'featured_testimonial_ids_json' => $schema->array()->description('Featured testimonial ids in order.'),
            'featured_journal_post_ids_json' => $schema->array()->description('Featured journal post ids in order.'),
            'investment_teaser' => $schema->string()->description('Investment teaser.'),
            'final_cta_heading' => $schema->string()->description('Closing call to action heading.')->max(255),
            'final_cta_body' => $schema->string()->description('Closing call to action body.'),
        ];
    }
}
