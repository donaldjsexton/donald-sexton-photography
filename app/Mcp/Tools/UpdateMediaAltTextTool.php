<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\Media;
use App\Services\Cms\MediaMetadata;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class UpdateMediaAltTextTool extends CmsTool
{
    protected string $name = 'update_media_alt_text';

    protected string $description = 'Set or clear the alt text of one media record. Does not change the file or where it is used.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly MediaMetadata $metadata,
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
            'alt_text' => Arr::get($this->metadata->rules(), 'alt_text'),
        ]);

        $media = Media::query()->find((int) $validated['media_id']);

        if (! $media instanceof Media) {
            $this->notFound('media');
        }

        $media->alt_text = $validated['alt_text'] ?? null;
        $media->save();

        return Response::structured(['media' => $this->payloads->media($media->fresh())]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'media_id' => $schema->integer()->description('Media id.')->required()->min(1),
            'alt_text' => $schema->string()->description('Descriptive alt text. Send an empty value to clear it.')->max(255),
        ];
    }
}
