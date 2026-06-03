<?php

namespace App\Observers;

use App\Jobs\BackfillSeoMetadata;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;

/**
 * Queues SEO metadata generation whenever published content is saved with a
 * missing title or description. Opt-in via services.anthropic.auto_seo so it
 * stays dormant in environments without an API key or a real queue worker.
 */
class SeoMetadataObserver
{
    public function saved(Model $model): void
    {
        if (! config('services.anthropic.auto_seo')) {
            return;
        }

        if ($this->isBlank($model->title ?? $model->name ?? null)) {
            return;
        }

        if (! $this->isBlank($model->seo_title) && ! $this->isBlank($model->seo_description)) {
            return;
        }

        if (! $this->isPublished($model)) {
            return;
        }

        BackfillSeoMetadata::dispatch($model);
    }

    /**
     * Venues have no draft state and are always public. Everything else must
     * be published with a due publish date before we spend tokens on it.
     */
    private function isPublished(Model $model): bool
    {
        if ($model instanceof Venue) {
            return true;
        }

        if (($model->status ?? null) !== 'published') {
            return false;
        }

        $publishedAt = $model->published_at ?? null;

        return $publishedAt === null || $publishedAt->lessThanOrEqualTo(now());
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
