<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait ManagesPolymorphicMedia
{
    /**
     * Attach one or more media items to the polymorphic owner.
     *
     * Appends new items to the end of the existing order.
     */
    protected function attachMediaToOwner(Model $owner, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['integer', 'exists:media,id'],
        ]);

        /** @var MorphToMany $relation */
        $relation = $owner->media();

        $existingIds = $relation->pluck('media.id')->all();
        $highestSort = (int) $relation->max('mediables.sort_order');
        $nextSort = $existingIds === [] ? 0 : $highestSort + 1;

        foreach ($validated['media_ids'] as $mediaId) {
            $mediaId = (int) $mediaId;

            if (in_array($mediaId, $existingIds, true)) {
                continue;
            }

            $relation->attach($mediaId, [
                'role' => 'gallery',
                'sort_order' => $nextSort++,
            ]);
        }

        return $this->galleryJson($owner);
    }

    /**
     * Detach a single media item from the polymorphic owner.
     */
    protected function detachMediaFromOwner(Model $owner, Media $media): JsonResponse
    {
        $owner->media()->detach($media->id);

        if (property_exists($owner, 'hero_media_id') || $owner->getAttribute('hero_media_id') !== null) {
            if ((int) $owner->getAttribute('hero_media_id') === $media->id) {
                $owner->setAttribute('hero_media_id', null);
                $owner->save();
            }
        }

        return $this->galleryJson($owner->fresh());
    }

    /**
     * Persist a new order for the owner's gallery.
     */
    protected function reorderOwnerMedia(Model $owner, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'media_ids' => ['required', 'array'],
            'media_ids.*' => ['integer', 'exists:media,id'],
        ]);

        /** @var MorphToMany $relation */
        $relation = $owner->media();
        $attachedIds = $relation->pluck('media.id')->all();
        $position = 0;

        foreach ($validated['media_ids'] as $mediaId) {
            $mediaId = (int) $mediaId;

            if (! in_array($mediaId, $attachedIds, true)) {
                continue;
            }

            $relation->updateExistingPivot($mediaId, ['sort_order' => $position++]);
        }

        return $this->galleryJson($owner);
    }

    /**
     * Promote a gallery item to be the hero for the owner.
     */
    protected function promoteOwnerHero(Model $owner, Media $media): JsonResponse
    {
        $owner->setAttribute('hero_media_id', $media->id);
        $owner->save();

        if (! $owner->media()->whereKey($media->id)->exists()) {
            $highestSort = (int) $owner->media()->max('mediables.sort_order');
            $owner->media()->attach($media->id, [
                'role' => 'hero',
                'sort_order' => $highestSort + 1,
            ]);
        }

        return $this->galleryJson($owner->fresh());
    }

    /**
     * Build the JSON payload describing the owner's gallery state.
     */
    protected function galleryJson(Model $owner): JsonResponse
    {
        $owner->loadMissing(['media' => fn ($query) => $query->orderBy('mediables.sort_order')]);

        $heroId = (int) ($owner->getAttribute('hero_media_id') ?? 0);

        $items = $owner->media->map(fn (Media $media) => [
            'id' => $media->id,
            'filename' => $media->filename,
            'alt_text' => $media->alt_text,
            'url' => $media->publicUrl(),
            'is_hero' => $media->id === $heroId,
            'edit_url' => route('admin.media.edit', $media),
        ])->all();

        return response()->json([
            'hero_media_id' => $heroId ?: null,
            'count' => count($items),
            'items' => $items,
        ]);
    }
}
