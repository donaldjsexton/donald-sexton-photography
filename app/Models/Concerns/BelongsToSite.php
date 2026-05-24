<?php

namespace App\Models\Concerns;

use App\Models\Site;
use App\Tenancy\CurrentSite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the current tenant. Adds a global scope that filters by
 * the resolved site and a creating hook that stamps site_id automatically.
 * When no site is resolved (e.g. before the default exists) it no-ops, so
 * migrations and early boot are unaffected.
 */
trait BelongsToSite
{
    public static function bootBelongsToSite(): void
    {
        static::addGlobalScope('site', function (Builder $query): void {
            $siteId = app(CurrentSite::class)->id();

            if ($siteId !== null) {
                $query->where($query->getModel()->getTable().'.site_id', $siteId);
            }
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('site_id') === null) {
                $model->setAttribute('site_id', app(CurrentSite::class)->id());
            }
        });
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return Builder<static>
     */
    public static function withoutSiteScope(): Builder
    {
        return static::query()->withoutGlobalScope('site');
    }
}
