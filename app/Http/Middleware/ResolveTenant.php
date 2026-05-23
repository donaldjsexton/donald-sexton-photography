<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Tenancy\CurrentSite;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * Subdomains that never map to a tenant.
     *
     * @var list<string>
     */
    private const RESERVED = ['www', 'admin', 'app', 'api', 'mail', 'ftp', 'cdn', 'assets', 'static'];

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

        if ($subdomain === '' || in_array($subdomain, self::RESERVED, true)) {
            return null;
        }

        $site = Site::query()->active()->where('subdomain', $subdomain)->first();

        if (! $site && app()->environment('production')) {
            abort(404);
        }

        return $site;
    }
}
