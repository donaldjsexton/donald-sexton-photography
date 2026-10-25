<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mark anonymous, cookie-free GET responses as cacheable at the CDN edge.
 *
 * Only successful responses that set no cookie are touched, so a page that
 * starts a session, flashes data, or errors is never shared between visitors.
 * Browsers still revalidate (max-age=0); the CDN may serve the page for
 * `seo.public_cache_ttl` seconds and a day more while it refreshes. Set the
 * ttl to 0 to disable.
 */
class CachePublicResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $ttl = (int) config('seo.public_cache_ttl', 0);

        if ($ttl > 0
            && $request->isMethodCacheable()
            && $response->getStatusCode() === Response::HTTP_OK
            && $response->headers->getCookies() === []
            && ! $response->headers->has('Set-Cookie')
        ) {
            $response->headers->set('Cache-Control', "public, max-age=0, s-maxage={$ttl}, stale-while-revalidate=86400");
        }

        return $response;
    }
}
