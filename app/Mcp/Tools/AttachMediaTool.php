<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\CmsMcpCredential;
use App\Models\Media;
use App\Models\Page;
use App\Models\WeddingStory;
use App\Services\Cms\OwnerMediaManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class AttachMediaTool extends CmsTool
{
    protected string $name = 'attach_media';

    protected string $description = 'Append an existing media record to the gallery of a draft story or draft page. Refuses owners that are not drafts.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly OwnerMediaManager $mediaManager,
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
            'media_id' => ['required', 'integer', 'exists:media,id'],
            'owner_type' => ['required', Rule::in(['story', 'page'])],
            'owner_id' => ['required', 'integer', 'min:1'],
        ]);

        $owner = $validated['owner_type'] === 'story'
            ? WeddingStory::query()->find((int) $validated['owner_id'])
            : Page::query()->find((int) $validated['owner_id']);

        if (! $owner instanceof Model) {
            $this->notFound($validated['owner_type']);
        }

        $this->mustBeDraft($validated['owner_type'], $owner->status);

        $this->mediaManager->attach($owner, [(int) $validated['media_id']]);

        return Response::structured([
            'owner_type' => $validated['owner_type'],
            'owner_id' => $owner->getKey(),
            'media' => $owner->media()->orderBy('mediables.sort_order')->get()->map(fn (Media $media): array => $this->payloads->media($media))->values()->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'media_id' => $schema->integer()->description('Media id to attach.')->required()->min(1),
            'owner_type' => $schema->string()->description('story or page.')->required(),
            'owner_id' => $schema->integer()->description('Draft story or page id.')->required()->min(1),
        ];
    }
}
