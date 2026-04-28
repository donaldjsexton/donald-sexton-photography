<?php

namespace App\Services\Seo;

use App\Models\JournalPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class JournalPostSeoGenerator
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public const TITLE_MAX_LENGTH = 60;

    public const DESCRIPTION_MAX_LENGTH = 160;

    private const BODY_WORD_LIMIT = 220;

    private const SYSTEM_PROMPT = <<<'PROMPT'
You write SEO meta titles and descriptions for the journal posts on the public site of Donald Sexton, a Tampa Bay wedding photographer based in Clearwater, Florida.

The journal mixes editorial real-wedding features, engagement sessions, brand work, planning advice, and venue spotlights. Match the post type's voice rather than treating every post like a wedding story.

Voice:
- Warm, photographer-led, present tense.
- Specific over generic. Reference the post type, the venue, the city, or a real detail from the post when one is provided.
- No marketing puffery, no "stunning"/"breathtaking"/"unforgettable", no clickbait.
- Never invent facts. If you don't know the venue or city, leave it out — do not guess.

Title rules:
- Plain text only, no quotes, no emoji, no trailing brand suffix (the site appends one separately).
- Lead with the most specific real-world detail available. For real weddings and engagements, lead with the couple or venue. For advice posts, lead with the question or topic. For venue spotlights, lead with the venue name and location. Title-case proper nouns.
- 45–60 characters. Hard ceiling 60.

Description rules:
- One or two sentences, 140–160 characters. Hard ceiling 160.
- Describe what the post is (a feature, a guide, advice, a venue spotlight) and what a reader will get out of it.
- End with a soft, natural close — not a hard CTA.

Return your output by calling the write_journal_post_seo tool exactly once.
PROMPT;

    public function generate(JournalPost $post): ?GeneratedSeo
    {
        $apiKey = (string) config('services.anthropic.key');

        if ($apiKey === '') {
            Log::warning('JournalPostSeoGenerator: missing ANTHROPIC_API_KEY.');

            return null;
        }

        $model = (string) config('services.anthropic.model');
        $version = (string) config('services.anthropic.version');

        $userMessage = $this->buildUserMessage($post);

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
                'tool_choice' => ['type' => 'tool', 'name' => 'write_journal_post_seo'],
                'messages' => [[
                    'role' => 'user',
                    'content' => $userMessage,
                ]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('JournalPostSeoGenerator request failed: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            Log::warning('JournalPostSeoGenerator non-200 from Anthropic: '.$response->status().' '.$response->body());

            return null;
        }

        $payload = $response->json();
        $blocks = $payload['content'] ?? [];

        $toolBlock = null;
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === 'write_journal_post_seo') {
                $toolBlock = $block;
                break;
            }
        }

        if ($toolBlock === null || ! is_array($toolBlock['input'] ?? null)) {
            Log::warning('JournalPostSeoGenerator: no write_journal_post_seo tool_use in response.');

            return null;
        }

        return $this->hydrate($toolBlock['input']);
    }

    private function buildUserMessage(JournalPost $post): string
    {
        $lines = [
            'Title: '.($post->title ?? '(untitled)'),
            'Post type: '.str_replace('_', ' ', (string) ($post->post_type ?? 'advice')),
        ];

        if (filled($post->author_name)) {
            $lines[] = 'Author: '.$post->author_name;
        }

        $venueNames = $post->relationLoaded('venues')
            ? $post->venues->pluck('name')->filter()->values()->all()
            : $post->venues()->pluck('name')->filter()->values()->all();

        if (! empty($venueNames)) {
            $lines[] = 'Venues: '.implode(', ', $venueNames);
        }

        $categoryNames = $post->relationLoaded('categories')
            ? $post->categories->pluck('name')->filter()->values()->all()
            : $post->categories()->pluck('name')->filter()->values()->all();

        if (! empty($categoryNames)) {
            $lines[] = 'Categories: '.implode(', ', $categoryNames);
        }

        $tagNames = $post->relationLoaded('tags')
            ? $post->tags->pluck('name')->filter()->values()->all()
            : $post->tags()->pluck('name')->filter()->values()->all();

        if (! empty($tagNames)) {
            $lines[] = 'Tags: '.implode(', ', $tagNames);
        }

        if (filled($post->published_at)) {
            $lines[] = 'Published: '.$post->published_at->format('F j, Y');
        }

        if (filled($post->excerpt)) {
            $lines[] = '';
            $lines[] = 'Excerpt:';
            $lines[] = trim((string) $post->excerpt);
        }

        $bodyText = $this->plainTextBody($post);

        if ($bodyText !== '') {
            $lines[] = '';
            $lines[] = 'Body (plain-text excerpt):';
            $lines[] = $bodyText;
        }

        return implode("\n", $lines);
    }

    private function plainTextBody(JournalPost $post): string
    {
        $body = (string) ($post->body ?? '');

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
            'name' => 'write_journal_post_seo',
            'description' => 'Record the SEO title and meta description for a journal post page.',
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
