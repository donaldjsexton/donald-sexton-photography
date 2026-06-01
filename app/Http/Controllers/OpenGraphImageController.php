<?php

namespace App\Http\Controllers;

use App\Models\JournalPost;
use App\Models\Media;
use App\Models\Venue;
use App\Models\WeddingStory;
use App\Support\OpenGraphImageRenderer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OpenGraphImageController extends Controller
{
    private const DISK = 'public';

    public function __construct(private readonly OpenGraphImageRenderer $renderer) {}

    public function story(string $slug): Response
    {
        $story = WeddingStory::published()
            ->with('heroMedia')
            ->where('slug', $slug)
            ->first();

        if (! $story) {
            throw new NotFoundHttpException;
        }

        return $this->serve(
            type: 'wedding-stories',
            cacheKey: $story->id.'-'.$story->updated_at?->timestamp,
            title: $story->title,
            eyebrow: $story->venue?->name ?? $story->location_name ?? 'Wedding Story',
            sourceMedia: $story->heroMedia,
        );
    }

    public function journalPost(string $slug): Response
    {
        $post = JournalPost::published()
            ->with('heroMedia')
            ->where('slug', $slug)
            ->first();

        if (! $post) {
            throw new NotFoundHttpException;
        }

        return $this->serve(
            type: 'journal',
            cacheKey: $post->id.'-'.$post->updated_at?->timestamp,
            title: $post->title,
            eyebrow: $post->post_type_label ?? 'Journal',
            sourceMedia: $post->heroMedia,
        );
    }

    public function venue(string $slug): Response
    {
        $venue = Venue::query()
            ->with('heroMedia')
            ->where('slug', $slug)
            ->first();

        if (! $venue) {
            throw new NotFoundHttpException;
        }

        $eyebrow = collect([$venue->city, $venue->state])->filter()->implode(', ');

        return $this->serve(
            type: 'venues',
            cacheKey: $venue->id.'-'.$venue->updated_at?->timestamp,
            title: $venue->name,
            eyebrow: $eyebrow ?: 'Venue',
            sourceMedia: $venue->heroMedia,
        );
    }

    private function serve(string $type, string $cacheKey, string $title, ?string $eyebrow, ?Media $sourceMedia): Response
    {
        $sourcePath = $this->resolveSourcePath($sourceMedia);

        $relativePath = $this->renderer->render(
            type: $type,
            cacheKey: $cacheKey,
            title: $title,
            eyebrow: $eyebrow,
            sourceImagePath: $sourcePath,
        );

        $disk = Storage::disk(self::DISK);
        $bytes = $disk->get($relativePath);

        if ($bytes === null) {
            throw new NotFoundHttpException;
        }

        return response($bytes, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /**
     * Resolve a local, memory-safe source path for the card photo. Prefers an
     * already-resized WebP derivative (capped at 1600px) over the full-size
     * original so a single render never decodes a 25MP file into a raw GD
     * bitmap — the kind of allocation that, under concurrent social-crawler
     * bursts, exhausts PHP-FPM workers and surfaces as Cloudflare 520s.
     */
    private function resolveSourcePath(?Media $media): ?string
    {
        if ($media === null || ! $media->path) {
            return null;
        }

        $disk = $media->disk ?? 'public';
        $storage = Storage::disk($disk);

        foreach ($this->preferredSourceCandidates($media) as $candidate) {
            if ($candidate === null || ! $storage->exists($candidate)) {
                continue;
            }

            $localPath = $storage->path($candidate);

            if (is_file($localPath)) {
                return $localPath;
            }
        }

        return null;
    }

    /**
     * Candidate disk paths in descending preference: bounded WebP derivatives
     * first, the full-size original only as a last resort (where the renderer's
     * dimension guard still protects against an oversized decode).
     *
     * @return array<int, string|null>
     */
    private function preferredSourceCandidates(Media $media): array
    {
        return [
            $media->webpVariantPath(1600),
            $media->webpVariantPath(1080),
            $media->webpVariantPath(640),
            $media->path,
        ];
    }
}
