<?php

namespace App\Tenancy;

use App\Models\Site;
use Illuminate\Support\Facades\Schema;

/**
 * Holds the tenant for the current request/process. Resolved explicitly by
 * the ResolveTenant middleware on web requests; everywhere else (CLI, queues,
 * tests) it lazily falls back to the default site so single-tenant behaviour
 * keeps working. Bound as a container singleton so each app instance — and
 * therefore each test — starts clean.
 */
class CurrentSite
{
    private ?Site $site = null;

    public function set(?Site $site): void
    {
        $this->site = $site;
    }

    public function get(): ?Site
    {
        // Only a concrete site is cached. A null result (e.g. resolved before
        // the default site exists, such as mid-migration) is never cached, so
        // resolution is retried once the default becomes available.
        if ($this->site === null && Schema::hasTable('sites')) {
            $this->site = Site::default();
        }

        return $this->site;
    }

    public function id(): ?int
    {
        return $this->get()?->id;
    }

    public function forget(): void
    {
        $this->site = null;
    }
}
