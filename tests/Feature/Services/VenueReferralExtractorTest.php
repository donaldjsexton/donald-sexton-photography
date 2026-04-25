<?php

namespace Tests\Feature\Services;

use App\Services\VenueReferral\VenueReferralExtractor;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VenueReferralExtractorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.anthropic.key', 'test-key');
        config()->set('services.anthropic.model', 'claude-haiku-4-5-20251001');
        config()->set('services.anthropic.version', '2023-06-01');
    }

    public function test_returns_null_when_api_key_missing(): void
    {
        config()->set('services.anthropic.key', '');

        $result = (new VenueReferralExtractor)->extract('subject', 'body');

        $this->assertNull($result);
    }

    public function test_parses_tool_use_response_into_extracted_referral(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'type' => 'tool_use',
                        'name' => 'record_referral',
                        'input' => [
                            'couple_names' => ['Stephanie Clancy', 'Scott Genirs'],
                            'event_date' => '2027-03-20',
                            'primary_email' => 'Clancy_stephanie@yahoo.com',
                            'secondary_email' => null,
                            'phone' => '727-495-9460',
                            'extraction_confidence' => 0.95,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = (new VenueReferralExtractor)->extract('New Client-Clancy Genirs 3.20.27', 'body');

        $this->assertNotNull($result);
        $this->assertSame(['Stephanie Clancy', 'Scott Genirs'], $result->coupleNames);
        $this->assertSame('2027-03-20', $result->eventDate->format('Y-m-d'));
        $this->assertSame('clancy_stephanie@yahoo.com', $result->primaryEmail);
        $this->assertNull($result->secondaryEmail);
        $this->assertSame('727-495-9460', $result->phone);
        $this->assertSame(0.95, $result->confidence);
    }

    public function test_sends_cache_control_on_system_prompt(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'record_referral',
                    'input' => [
                        'couple_names' => ['A B'],
                        'event_date' => null,
                        'primary_email' => null,
                        'secondary_email' => null,
                        'phone' => null,
                        'extraction_confidence' => 0.1,
                    ],
                ]],
            ], 200),
        ]);

        (new VenueReferralExtractor)->extract('s', 'b');

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['system'][0]['cache_control']['type'] ?? null) === 'ephemeral'
                && ($body['tool_choice']['name'] ?? null) === 'record_referral';
        });
    }

    public function test_returns_null_when_response_lacks_tool_use_block(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'I cannot extract this.'],
                ],
            ], 200),
        ]);

        $result = (new VenueReferralExtractor)->extract('subject', 'body');

        $this->assertNull($result);
    }

    public function test_handles_missing_optional_fields(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'record_referral',
                    'input' => [
                        'couple_names' => ['Catherine Therrien', 'Pete Piazza'],
                        'event_date' => '2026-10-31',
                        'primary_email' => 'cathy.therrien@yahoo.com',
                        'secondary_email' => null,
                        'phone' => null,
                        'extraction_confidence' => 0.7,
                    ],
                ]],
            ], 200),
        ]);

        $result = (new VenueReferralExtractor)->extract('subject', 'body');

        $this->assertNotNull($result);
        $this->assertNull($result->phone);
        $this->assertFalse($result->isComplete());
    }

    public function test_returns_null_on_non_200_response(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'rate limited'], 429),
        ]);

        $result = (new VenueReferralExtractor)->extract('subject', 'body');

        $this->assertNull($result);
    }
}
