<?php

namespace App\Services\Cms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class OwnerMediaManager
{
    /**
     * Append media to the end of an owner's gallery, skipping items that are
     * already attached.
     *
     * @param  list<int|string>  $mediaIds
     */
    public function attach(Model $owner, array $mediaIds): void
    {
        /** @var MorphToMany $relation */
        $relation = $owner->media();

        $existingIds = $relation->pluck('media.id')->all();
        $highestSort = (int) $relation->max('mediables.sort_order');
        $nextSort = $existingIds === [] ? 0 : $highestSort + 1;

        foreach ($mediaIds as $mediaId) {
            $mediaId = (int) $mediaId;

            if (in_array($mediaId, $existingIds, true)) {
                continue;
            }

            $relation->attach($mediaId, [
                'role' => 'gallery',
                'sort_order' => $nextSort++,
            ]);
        }
    }
}
