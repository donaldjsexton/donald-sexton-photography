<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\Venue;
use App\Services\Cms\VenueManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class UpdateVenueTool extends CmsTool
{
    protected string $name = 'update_venue';

    protected string $description = 'Update public venue copy, SEO fields, hero, and featured flag. Venues have no draft state, so changes are live. Billing, referral, and portal fields cannot be changed here.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly VenueManager $venues,
        private readonly CmsPayloads $payloads,
    ) {
        parent::__construct($gate);
    }

    protected function ability(): string
    {
        return CmsMcpCredential::VENUES_WRITE;
    }

    protected function run(Request $request): ResponseFactory
    {
        $venue = Venue::query()->find((int) $request->get('venue_id'));

        if (! $venue instanceof Venue) {
            $this->notFound('venue');
        }

        $rules = $this->partial(Arr::only($this->venues->rules($venue), VenueManager::PUBLIC_FIELDS));
        $this->venues->save($venue, $this->validated($request, $rules), partial: true);

        return Response::structured(['venue' => $this->payloads->venue($venue->fresh(), true)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'venue_id' => $schema->integer()->description('Venue id.')->required()->min(1),
            'name' => $schema->string()->description('Venue name.')->max(255),
            'slug' => $schema->string()->description('URL slug.')->max(255),
            'city' => $schema->string()->description('City.')->max(255),
            'state' => $schema->string()->description('State.')->max(255),
            'region' => $schema->string()->description('Region.')->max(255),
            'headline' => $schema->string()->description('Headline.')->max(255),
            'summary' => $schema->string()->description('Summary.'),
            'body' => $schema->string()->description('Body HTML.'),
            'hero_media_id' => $schema->integer()->description('Hero media id.')->min(1),
            'website_url' => $schema->string()->description('Website URL.')->max(255),
            'google_places_id' => $schema->string()->description('Google Places id.')->max(255),
            'is_featured' => $schema->boolean()->description('Featured venue flag.'),
            'seo_title' => $schema->string()->description('SEO title.')->max(255),
            'seo_description' => $schema->string()->description('SEO description.'),
            'faqs_text' => $schema->string()->description('One FAQ per line as Question | Answer.')->max(8000),
        ];
    }
}
