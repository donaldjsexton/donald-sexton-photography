<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\MediaUploadService;
use App\Mcp\Tools\Concerns\RegistersForAbility;
use App\Models\CmsMcpCredential;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class UploadMediaTool extends Tool
{
    use RegistersForAbility;

    protected string $name = 'upload_media';

    protected string $description = 'Upload one JPEG, PNG, WebP, or GIF as base64. Retries with the same idempotency key return the original media row. Uploading does not publish a story or attach the image.';

    public function __construct(
        private readonly CmsMcpGate $gate,
        private readonly MediaUploadService $uploads,
    ) {}

    public function shouldRegister(): bool
    {
        return $this->credentialAllows(CmsMcpCredential::UPLOAD);
    }

    public function handle(Request $request): ResponseFactory
    {
        $credential = $this->gate->authorize(CmsMcpCredential::UPLOAD);

        /** @var array{filename?: string|null, content_base64: string, alt_text?: string|null, idempotency_key: string} $validated */
        $validated = $request->validate([
            'filename' => ['nullable', 'string', 'max:255'],
            'content_base64' => ['required', 'string'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:128', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ]);

        return Response::structured($this->uploads->store($credential, $validated));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'filename' => $schema->string()->description('Original filename. Path characters are removed.')->max(255),
            'content_base64' => $schema->string()->description('Base64-encoded image bytes. No remote URL is fetched.')->required(),
            'alt_text' => $schema->string()->description('Descriptive alt text stored on the media record.')->max(255),
            'idempotency_key' => $schema->string()->description('Stable key for this upload. Reuse it to retry without creating a second image.')->required()->min(8)->max(128),
        ];
    }
}
