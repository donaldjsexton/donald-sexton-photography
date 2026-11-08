<?php

namespace App\Mcp;

use App\Models\CmsMcpCredential;
use App\Models\CmsMcpUploadReceipt;
use App\Models\Media;
use App\Services\Media\MediaUploader;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MediaUploadService
{
    public function __construct(private readonly MediaUploader $uploader) {}

    /**
     * @param  array{filename?: string|null, content_base64: string, alt_text?: string|null, idempotency_key: string}  $input
     * @return array<string, mixed>
     */
    public function store(CmsMcpCredential $credential, array $input): array
    {
        $binary = base64_decode($input['content_base64'], true);

        if ($binary === false) {
            throw ValidationException::withMessages([
                'content_base64' => 'Image content must be valid base64.',
            ]);
        }

        $maxBytes = (int) config('cms-mcp.max_upload_bytes');

        if (strlen($binary) === 0 || strlen($binary) > $maxBytes) {
            throw ValidationException::withMessages([
                'content_base64' => 'Image content must be between 1 and '.$maxBytes.' bytes after decoding.',
            ]);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary) ?: '';
        $allowed = config('cms-mcp.allowed_mime_types');

        if (! is_array($allowed) || ! in_array($mime, $allowed, true) || getimagesizefromstring($binary) === false) {
            throw ValidationException::withMessages([
                'content_base64' => 'Only JPEG, PNG, WebP, and GIF images are accepted.',
            ]);
        }

        $sha = hash('sha256', $binary);

        return DB::transaction(function () use ($credential, $input, $binary, $mime, $sha): array {
            $existing = CmsMcpUploadReceipt::query()
                ->where('cms_mcp_credential_id', $credential->id)
                ->where('idempotency_key', $input['idempotency_key'])
                ->lockForUpdate()
                ->first();

            if ($existing instanceof CmsMcpUploadReceipt) {
                return $this->replay($existing, $sha);
            }

            $media = $this->persistFile($binary, $mime, $input);

            try {
                CmsMcpUploadReceipt::query()->create([
                    'cms_mcp_credential_id' => $credential->id,
                    'idempotency_key' => $input['idempotency_key'],
                    'content_sha256' => $sha,
                    'media_id' => $media->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                $disk = $media->disk ?? 'public';
                $path = $media->path;
                $media->delete();

                if (filled($path)) {
                    Storage::disk($disk)->delete($path);
                }
                $existing = CmsMcpUploadReceipt::query()
                    ->where('cms_mcp_credential_id', $credential->id)
                    ->where('idempotency_key', $input['idempotency_key'])
                    ->first();

                if (! $existing instanceof CmsMcpUploadReceipt) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'This upload could not be completed. Retry it.',
                    ]);
                }

                return $this->replay($existing, $sha);
            }

            return $this->present($media, false);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function replay(CmsMcpUploadReceipt $receipt, string $sha): array
    {
        if (! hash_equals($receipt->content_sha256, $sha)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'This idempotency key was already used for different image bytes.',
            ]);
        }

        $media = $receipt->media;

        if (! $media instanceof Media) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The original upload for this key is no longer available.',
            ]);
        }

        return $this->present($media, true);
    }

    /**
     * @param  array{filename?: string|null, alt_text?: string|null}  $input
     */
    private function persistFile(string $binary, string $mime, array $input): Media
    {
        $path = tempnam(sys_get_temp_dir(), 'cms-mcp-');

        if ($path === false) {
            throw ValidationException::withMessages([
                'content_base64' => 'The image could not be stored.',
            ]);
        }

        try {
            file_put_contents($path, $binary);
            $upload = new UploadedFile($path, $this->filename($input['filename'] ?? null, $mime), $mime, null, true);

            return $this->uploader->store($upload, [
                'alt_text' => $input['alt_text'] ?? null,
            ]);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function filename(?string $filename, string $mime): string
    {
        $filename = basename(str_replace('\\', '/', (string) $filename));
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) ?: '';
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'img',
        };

        if ($filename === '' || $filename === '.' || $filename === '..') {
            return 'upload.'.$extension;
        }

        return $filename;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Media $media, bool $replayed): array
    {
        return [
            'replayed' => $replayed,
            'media' => [
                'id' => $media->id,
                'filename' => $media->filename,
                'alt_text' => $media->alt_text,
                'mime_type' => $media->mime_type,
                'width' => $media->width,
                'height' => $media->height,
            ],
        ];
    }
}
