<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Canonical public URLs have no trailing slash. /about/ was returning 200
 * with a canonical pointing at /about, so send slash-suffixed GET and HEAD
 * requests to the slash-free path. The site root is unchanged.
 */
class RedirectTrailingSlashes
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $path = $request->getPathInfo();

        if ($path === '/' || ! str_ends_with($path, '/')) {
            return $next($request);
        }

        $trimmed = rtrim($path, '/') ?: '/';
        $query = $request->getQueryString();
        $target = $trimmed.($query !== null && $query !== '' ? '?'.$query : '');

        return redirect($target, 301);
    }
}
