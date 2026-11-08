<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectUnexpectedMcpOrigin
{
    /**
     * Browser clients send Origin. A missing Origin is a non-browser caller,
     * which is how a remote MCP connector presents itself. A present Origin
     * must be this application so a hostile page cannot call the endpoint.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if (! is_string($origin) || $origin === '' || strtolower($origin) === 'null') {
            return $next($request);
        }

        $originHost = parse_url($origin, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($originHost) || ! is_string($appHost) || strcasecmp($originHost, $appHost) !== 0) {
            return response()->json([
                'message' => 'Origin is not allowed.',
            ], 403);
        }

        return $next($request);
    }
}
