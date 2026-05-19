<?php

namespace App\Services\Media;

use App\Models\Media;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AltTextGenerator
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public const ALT_TEXT_MAX_LENGTH = 125;

    /**
     * Widths of already-generated WebP derivatives to prefer over the
     * full-size original, in order. Claude downsamples anything past
     * ~1568px anyway, so a small derivative is equivalent for alt text
     * while keeping peak memory in the hundreds of KB instead of tens
     * of MB (original + base64 + JSON body copies of a 25MP photo).
     */
    private const PREFERRED_VARIANT_WIDTHS = [1080, 640];

    private const SYSTEM_PROMPT = <<<'PROMPT'
You write concise, descriptive alt text for wedding and portrait photographs by Donald Sexton, a Tampa Bay wedding photographer based in Clearwater, Florida.

Rules:
- Describe what is actually visible: people, action, setting, lighting, mood.
- 8 to 20 words. No more.
- Do not start with "A photo of", "An image of", or "Picture of".
- Do not use "beautiful", "stunning", "breathtaking", "gorgeous", or other filler adjectives.
- Use the event or venue name if it is provided in the context — weave it in naturally.
- Return only the alt text — no trailing punctuation, no quotation marks, nothing else.
PROMPT;

    public function generate(Media $media, ?string $context = null): ?string
    {
        $apiKey = (string) config('services.anthropic.key');

        if ($apiKey === '') {
            Log::warning('AltTextGenerator: missing ANTHROPIC_API_KEY.');

            return null;
        }

        $imageSource = $this->base64ImageSource($media);

        if ($imageSource === null) {
            return null;
        }

        $version = (string) config('services.anthropic.version');
        $userContent = $this->buildUserContent($imageSource, $context);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => $version,
                'content-type' => 'application/json',
            ])->timeout(45)->post(self::ENDPOINT, [
                'model' => 'claude-haiku-4-5-20251001',
                'max_tokens' => 80,
                'system' => [[
                    'type' => 'text',
                    'text' => self::SYSTEM_PROMPT,
                    'cache_control' => ['type' => 'ephemeral'],
                ]],
                'tools' => [$this->toolDefinition()],
                'tool_choice' => ['type' => 'tool', 'name' => 'write_alt_text'],
                'messages' => [[
                    'role' => 'user',
                    'content' => $userContent,
                ]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('AltTextGenerator request failed: '.$e->getMessage(), ['media_id' => $media->id]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('AltTextGenerator non-200 from Anthropic: '.$response->status(), [
                'media_id' => $media->id,
                'body' => $response->body(),
            ]);

            return null;
        }

        $payload = $response->json();
        $blocks = $payload['content'] ?? [];

        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === 'write_alt_text') {
                return $this->normalizeAltText($block['input']['alt_text'] ?? null);
            }
        }

        Log::warning('AltTextGenerator: no write_alt_text tool_use in response.', ['media_id' => $media->id]);

        return null;
    }

    /**
     * @return array{type: string, media_type: string, data: string}|null
     */
    private function base64ImageSource(Media $media): ?array
    {
        if (! $media->path) {
            return null;
        }

        $disk = $media->disk ?? 'public';
        $storage = Storage::disk($disk);

        $path = $this->preferredImagePath($media, $storage);

        if ($path === null) {
            Log::warning('AltTextGenerator: file not found on disk.', ['media_id' => $media->id, 'path' => $media->path]);

            return null;
        }

        $contents = $storage->get($path);

        if ($contents === null || $contents === '') {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mediaType = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        $encoded = base64_encode($contents);
        unset($contents);

        return [
            'type' => 'base64',
            'media_type' => $mediaType,
            'data' => $encoded,
        ];
    }

    /**
     * Resolve the smallest already-generated WebP derivative that exists
     * on disk, falling back to the full-size original only when none is
     * available. This keeps a multi-MB original (and its base64/JSON
     * copies) from being held in memory all at once.
     */
    private function preferredImagePath(Media $media, Filesystem $storage): ?string
    {
        $candidates = [];

        foreach (self::PREFERRED_VARIANT_WIDTHS as $width) {
            $candidates[] = $media->webpVariantPath($width);
        }

        $candidates[] = $media->webpPath();

        foreach (array_filter($candidates) as $candidate) {
            if ($storage->exists($candidate)) {
                return $candidate;
            }
        }

        return $media->path && $storage->exists($media->path) ? $media->path : null;
    }

    /**
     * @param  array{type: string, media_type: string, data: string}  $imageSource
     * @return array<int, array<string, mixed>>
     */
    private function buildUserContent(array $imageSource, ?string $context): array
    {
        $content = [];

        if (filled($context)) {
            $content[] = [
                'type' => 'text',
                'text' => 'Context: '.$context,
            ];
        }

        $content[] = [
            'type' => 'image',
            'source' => $imageSource,
        ];

        $content[] = [
            'type' => 'text',
            'text' => 'Write the alt text for this photograph.',
        ];

        return $content;
    }

    private function normalizeAltText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim(preg_replace('/\s+/u', ' ', $value) ?? $value, " \t\n\r\0\x0B.\"'");

        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) > self::ALT_TEXT_MAX_LENGTH) {
            return rtrim(mb_substr($trimmed, 0, self::ALT_TEXT_MAX_LENGTH - 1), " \t\n\r\0\x0B.,;:-").'…';
        }

        return $trimmed;
    }

    /**
     * @return array<string, mixed>
     */
    private function toolDefinition(): array
    {
        return [
            'name' => 'write_alt_text',
            'description' => 'Record the alt text for a photograph.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'alt_text' => [
                        'type' => 'string',
                        'description' => 'Concise, descriptive alt text for the image. 8–20 words. No leading "A photo of".',
                    ],
                ],
                'required' => ['alt_text'],
            ],
        ];
    }
}
