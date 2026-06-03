<?php

namespace App\Services\Seo;

use App\Models\Venue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VenueSeoGenerator
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public const TITLE_MAX_LENGTH = 60;

    public const DESCRIPTION_MAX_LENGTH = 160;

    private const BODY_WORD_LIMIT = 220;

    private const SYSTEM_PROMPT = <<<'PROMPT'
You write SEO meta titles and descriptions for the wedding-venue guide pages on the public site of Donald Sexton, a Tampa Bay wedding photographer based in Clearwater, Florida.

Each page is a photographer's guide to one real wedding venue — what it's like to shoot there, the light, the spaces, and real weddings he has photographed at it. The audience is couples actively planning a wedding at (or considering) that specific venue.

Voice:
- Warm, photographer-led, present tense.
- Specific over generic. Lead with the venue's real name and its city when provided.
- No marketing puffery, no "stunning"/"breathtaking"/"unforgettable", no clickbait.
- Never invent facts. If you don't know a detail, leave it out — do not guess.

Title rules:
- Plain text only, no quotes, no emoji, no trailing brand suffix (the site appends one separately).
- Lead with the venue name. Title-case proper nouns. Including the city is encouraged when it fits.
- 45–60 characters. Hard ceiling 60.

Description rules:
- One or two sentences, 140–160 characters. Hard ceiling 160.
- Tell a couple planning a wedding there what they'll find: a photographer's perspective on the venue and real weddings shot on site.
- End with a soft, natural close — not a hard CTA.

Return your output by calling the write_venue_seo tool exactly once.
PROMPT;

    public function generate(Venue $venue): ?GeneratedSeo
    {
        $apiKey = (string) config('services.anthropic.key');

        if ($apiKey === '') {
            Log::warning('VenueSeoGenerator: missing ANTHROPIC_API_KEY.');

            return null;
        }

        $model = (string) config('services.anthropic.model');
        $version = (string) config('services.anthropic.version');

        $userMessage = $this->buildUserMessage($venue);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => $version,
                'content-type' => 'application/json',
            ])->timeout(30)->post(self::ENDPOINT, [
                'model' => $model,
                'max_tokens' => 512,
                'system' => [[
                    'type' => 'text',
                    'text' => self::SYSTEM_PROMPT,
                    'cache_control' => ['type' => 'ephemeral'],
                ]],
                'tools' => [$this->toolDefinition()],
                'tool_choice' => ['type' => 'tool', 'name' => 'write_venue_seo'],
                'messages' => [[
                    'role' => 'user',
                    'content' => $userMessage,
                ]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('VenueSeoGenerator request failed: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            Log::warning('VenueSeoGenerator non-200 from Anthropic: '.$response->status().' '.$response->body());

            return null;
        }

        $payload = $response->json();
        $blocks = $payload['content'] ?? [];

        $toolBlock = null;
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === 'write_venue_seo') {
                $toolBlock = $block;
                break;
            }
        }

        if ($toolBlock === null || ! is_array($toolBlock['input'] ?? null)) {
            Log::warning('VenueSeoGenerator: no write_venue_seo tool_use in response.');

            return null;
        }

        return $this->hydrate($toolBlock['input']);
    }

    private function buildUserMessage(Venue $venue): string
    {
        $lines = [
            'Venue: '.($venue->name ?? '(unnamed)'),
        ];

        $cityState = trim(implode(', ', array_filter([$venue->city, $venue->state])));

        if ($cityState !== '') {
            $lines[] = 'Location: '.$cityState;
        } elseif (filled($venue->region)) {
            $lines[] = 'Region: '.$venue->region;
        }

        if (filled($venue->headline)) {
            $lines[] = 'Headline: '.trim((string) $venue->headline);
        }

        if (filled($venue->summary)) {
            $lines[] = '';
            $lines[] = 'Summary:';
            $lines[] = trim((string) $venue->summary);
        }

        $bodyText = $this->plainTextBody($venue);

        if ($bodyText !== '') {
            $lines[] = '';
            $lines[] = 'Body (plain-text excerpt):';
            $lines[] = $bodyText;
        }

        return implode("\n", $lines);
    }

    private function plainTextBody(Venue $venue): string
    {
        $body = (string) ($venue->body ?? '');

        if ($body === '') {
            return '';
        }

        $stripped = trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $collapsed = preg_replace('/\s+/u', ' ', $stripped) ?? $stripped;

        return Str::words($collapsed, self::BODY_WORD_LIMIT);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function hydrate(array $input): ?GeneratedSeo
    {
        $title = $this->normalizeString($input['title'] ?? null, self::TITLE_MAX_LENGTH);
        $description = $this->normalizeString($input['description'] ?? null, self::DESCRIPTION_MAX_LENGTH);

        if ($title === null || $description === null) {
            return null;
        }

        return new GeneratedSeo(title: $title, description: $description);
    }

    private function normalizeString(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) <= $maxLength) {
            return $trimmed;
        }

        return rtrim(mb_substr($trimmed, 0, $maxLength - 1), " \t\n\r\0\x0B.,;:-").'…';
    }

    /**
     * @return array<string, mixed>
     */
    private function toolDefinition(): array
    {
        return [
            'name' => 'write_venue_seo',
            'description' => 'Record the SEO title and meta description for a wedding-venue guide page.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'title' => [
                        'type' => 'string',
                        'description' => 'SEO meta title, 45–60 characters. No quotes, no brand suffix.',
                    ],
                    'description' => [
                        'type' => 'string',
                        'description' => 'SEO meta description, 140–160 characters. One or two sentences.',
                    ],
                ],
                'required' => ['title', 'description'],
            ],
        ];
    }
}
