<?php

namespace App\Services\VenueReferral;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VenueReferralExtractor
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const SYSTEM_PROMPT = <<<'PROMPT'
You extract structured client-referral details from emails sent by wedding-venue coordinators to wedding photographers.

The email contains the names of an engaged couple, their event/wedding date, contact email(s), and contact phone number. Formatting varies widely:
- Dates may use "M.D.YY", "M/D/YY", or other forms. Two-digit years are 2000+ (e.g. "3.20.27" → 2027-03-20).
- Names may be on one line, separated by "&" / "and" / "-", or on separate lines.
- Phone may have a "Phone:" label or be bare.
- Sometimes two emails are listed for the couple — return both, primary first.

Return your extraction by calling the record_referral tool exactly once. Only return what you are confident is in the email. Set extraction_confidence to your honest 0–1 estimate that all four required fields (couple_names, event_date, primary_email, phone) are correct. If a field is missing or you cannot tell, return null for that field and lower the confidence accordingly.
PROMPT;

    public function extract(string $subject, string $body): ?ExtractedReferral
    {
        $apiKey = (string) config('services.anthropic.key');

        if ($apiKey === '') {
            Log::warning('VenueReferralExtractor: missing ANTHROPIC_API_KEY.');

            return null;
        }

        $model = (string) config('services.anthropic.model');
        $version = (string) config('services.anthropic.version');

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => $version,
                'content-type' => 'application/json',
            ])->timeout(30)->post(self::ENDPOINT, [
                'model' => $model,
                'max_tokens' => 1024,
                'system' => [[
                    'type' => 'text',
                    'text' => self::SYSTEM_PROMPT,
                    'cache_control' => ['type' => 'ephemeral'],
                ]],
                'tools' => [$this->toolDefinition()],
                'tool_choice' => ['type' => 'tool', 'name' => 'record_referral'],
                'messages' => [[
                    'role' => 'user',
                    'content' => "Subject: {$subject}\n\nBody:\n{$body}",
                ]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('VenueReferralExtractor request failed: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            Log::warning('VenueReferralExtractor non-200 from Anthropic: '.$response->status().' '.$response->body());

            return null;
        }

        $payload = $response->json();
        $blocks = $payload['content'] ?? [];

        $toolBlock = null;
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === 'record_referral') {
                $toolBlock = $block;
                break;
            }
        }

        if ($toolBlock === null || ! is_array($toolBlock['input'] ?? null)) {
            Log::warning('VenueReferralExtractor: no record_referral tool_use in response.');

            return null;
        }

        return $this->hydrate($toolBlock['input']);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function hydrate(array $input): ExtractedReferral
    {
        $names = [];

        foreach ($input['couple_names'] ?? [] as $name) {
            if (! is_string($name)) {
                continue;
            }

            $trimmed = trim($name);

            if ($trimmed !== '') {
                $names[] = $trimmed;
            }
        }

        $eventDate = null;
        $rawDate = $input['event_date'] ?? null;

        if (is_string($rawDate) && $rawDate !== '') {
            try {
                $eventDate = Carbon::parse($rawDate)->startOfDay();
            } catch (\Throwable) {
                $eventDate = null;
            }
        }

        return new ExtractedReferral(
            coupleNames: $names,
            eventDate: $eventDate,
            primaryEmail: $this->normalizeEmail($input['primary_email'] ?? null),
            secondaryEmail: $this->normalizeEmail($input['secondary_email'] ?? null),
            phone: $this->normalizePhone($input['phone'] ?? null),
            confidence: (float) ($input['extraction_confidence'] ?? 0.0),
        );
    }

    private function normalizeEmail(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        return $value === '' ? null : $value;
    }

    private function normalizePhone(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function toolDefinition(): array
    {
        return [
            'name' => 'record_referral',
            'description' => 'Record the parsed couple referral.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'couple_names' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Full names of each person in the couple, e.g. ["Stephanie Clancy", "Scott Genirs"].',
                    ],
                    'event_date' => [
                        'type' => ['string', 'null'],
                        'description' => 'ISO 8601 date YYYY-MM-DD. Two-digit years are 2000+ (e.g. "3.20.27" => "2027-03-20"). Null if not present.',
                    ],
                    'primary_email' => [
                        'type' => ['string', 'null'],
                        'description' => "Couple's primary email address. Null if not present.",
                    ],
                    'secondary_email' => [
                        'type' => ['string', 'null'],
                        'description' => "Couple's secondary email address if a second is listed. Null otherwise.",
                    ],
                    'phone' => [
                        'type' => ['string', 'null'],
                        'description' => "Couple's phone number, in any human format.",
                    ],
                    'extraction_confidence' => [
                        'type' => 'number',
                        'description' => '0-1 self-rated confidence that all required fields are correct.',
                    ],
                ],
                'required' => ['couple_names', 'event_date', 'primary_email', 'phone', 'extraction_confidence'],
            ],
        ];
    }
}
