<?php

namespace App\Services\Media;

use App\Models\Media;

class MediaDuplicateAuditor
{
    public function audit(iterable $mediaItems): array
    {
        $seen = 0;
        $missing = 0;
        $groups = [];

        foreach ($mediaItems as $media) {
            if (! $media instanceof Media) {
                continue;
            }

            $seen++;
            $absolutePath = $this->absolutePathFor($media);

            if ($absolutePath === null || ! is_file($absolutePath)) {
                $missing++;

                continue;
            }

            $bytes = filesize($absolutePath);
            $hash = hash_file('sha256', $absolutePath);

            if ($hash === false || $bytes === false) {
                $missing++;

                continue;
            }

            $key = $bytes.':'.$hash;
            $groups[$key] ??= [
                'sha256' => $hash,
                'bytes' => (int) $bytes,
                'items' => [],
            ];
            $groups[$key]['items'][] = $this->serializeMedia($media, $absolutePath, (int) $bytes);
        }

        $duplicateGroups = array_values(array_filter($groups, fn (array $group) => count($group['items']) > 1));

        usort($duplicateGroups, function (array $left, array $right): int {
            $byCount = count($right['items']) <=> count($left['items']);

            if ($byCount !== 0) {
                return $byCount;
            }

            return $right['bytes'] <=> $left['bytes'];
        });

        $duplicateFiles = array_sum(array_map(fn (array $group) => count($group['items']), $duplicateGroups));
        $reclaimableBytes = array_sum(array_map(
            fn (array $group) => max(0, count($group['items']) - 1) * (int) $group['bytes'],
            $duplicateGroups
        ));

        return [
            'summary' => [
                'files_seen' => $seen,
                'files_missing' => $missing,
                'duplicate_groups' => count($duplicateGroups),
                'duplicate_files' => $duplicateFiles,
                'reclaimable_bytes' => $reclaimableBytes,
            ],
            'groups' => $duplicateGroups,
        ];
    }

    private function serializeMedia(Media $media, string $absolutePath, int $bytes): array
    {
        $usageCounts = [
            'pages' => (int) ($media->pages_count ?? 0),
            'wedding_stories' => (int) ($media->wedding_stories_count ?? 0),
            'journal_posts' => (int) ($media->journal_posts_count ?? 0),
            'venues' => (int) ($media->venues_count ?? 0),
        ];

        return [
            'id' => $media->id,
            'disk' => $media->disk,
            'path' => $media->path,
            'absolute_path' => $absolutePath,
            'filename' => $media->filename,
            'mime_type' => $media->mime_type,
            'bytes' => $bytes,
            'usage_total' => array_sum($usageCounts),
            'usage' => $usageCounts,
        ];
    }

    private function absolutePathFor(Media $media): ?string
    {
        $disk = $media->disk ?: 'public';
        $path = trim((string) $media->path);

        if ($path === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Facades\Storage::disk($disk)->path($path);
        } catch (\Throwable) {
            return null;
        }
    }
}
