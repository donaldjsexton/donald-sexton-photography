<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\Media;
use App\Services\Media\AltTextContext;
use App\Services\Media\AltTextGenerator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class GenerateAltTextTool extends CmsTool
{
    protected string $name = 'generate_alt_text';

    protected string $description = 'Generate alt text for one image with the same generator as media:generate-alt-text. Keeps existing alt text unless overwrite is true. Calls the Anthropic API.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly AltTextGenerator $generator,
        private readonly CmsPayloads $payloads,
    ) {
        parent::__construct($gate);
    }

    protected function ability(): string
    {
        return CmsMcpCredential::MEDIA_WRITE;
    }

    protected function run(Request $request): ResponseFactory
    {
        $validated = $this->validated($request, [
            'media_id' => ['required', 'integer', 'min:1'],
            'overwrite' => ['sometimes', 'boolean'],
        ]);

        $media = Media::query()->find((int) $validated['media_id']);

        if (! $media instanceof Media) {
            $this->notFound('media');
        }

        if (filled($media->alt_text) && ! (bool) ($validated['overwrite'] ?? false)) {
            return Response::structured(['generated' => false, 'media' => $this->payloads->media($media)]);
        }

        $altText = $this->generator->generate($media, AltTextContext::for($media));

        if ($altText === null) {
            return Response::structured(['generated' => false, 'media' => $this->payloads->media($media)]);
        }

        $media->forceFill(['alt_text' => $altText])->save();

        return Response::structured(['generated' => true, 'media' => $this->payloads->media($media->fresh())]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'media_id' => $schema->integer()->description('Media id.')->required()->min(1),
            'overwrite' => $schema->boolean()->description('Replace existing alt text. Defaults to false.'),
        ];
    }
}
