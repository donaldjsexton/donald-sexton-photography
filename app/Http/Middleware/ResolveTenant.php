<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Models\SiteDomain;
use App\Tenancy\CurrentSite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $appDomain = strtolower((string) config('app.domain'));

        $site = $this->resolveFromSubdomain($host, $appDomain)
            ?? $this->resolveFromCustomDomain($host)
            ?? Site::query()->active()->where('primary_domain', $host)->first()
            ?? Site::default();

        app(CurrentSite::class)->set($site);

        return $next($request);
    }

    private function resolveFromSubdomain(string $host, string $appDomain): ?Site
    {
        if ($appDomain === '' || ! str_ends_with($host, '.'.$appDomain)) {
            return null;
        }

        $subdomain = trim(substr($host, 0, -(strlen($appDomain) + 1)), '.');

        if ($subdomain === '' || Site::isReservedSubdomain($subdomain)) {
            return null;
        }

        $site = Site::query()->active()->where('subdomain', $subdomain)->first();

        if (! $site && app()->environment('production')) {
            abort(404);
        }

        return $site;
    }

    private function resolveFromCustomDomain(string $host): ?Site
    {
        $domain = SiteDomain::query()->verified()->where('host', $host)->first();

        return $domain?->site()->active()->first();
    }
}
