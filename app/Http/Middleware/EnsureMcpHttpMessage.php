<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMcpHttpMessage
{
    /**
     * Protocol versions accepted by the installed Laravel MCP server.
     *
     * @var list<string>
     */
    public const PROTOCOL_VERSIONS = [
        '2025-11-25',
        '2025-06-18',
        '2025-03-26',
        '2024-11-05',
    ];

    /**
     * Streamable HTTP clients must accept both a JSON body and an event stream.
     * This middleware is attached to the POST endpoint only. GET stays a 405,
     * which is how a server declines the optional SSE listen channel.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $contentType = strtolower((string) $request->header('Content-Type', ''));

        if (! str_contains($contentType, 'application/json')) {
            return response()->json([
                'message' => 'Content-Type must be application/json.',
            ], 415);
        }

        $accept = strtolower((string) $request->header('Accept', ''));

        if (! str_contains($accept, 'application/json') || ! str_contains($accept, 'text/event-stream')) {
            return response()->json([
                'message' => 'Accept must include application/json and text/event-stream.',
            ], 406);
        }

        $version = $request->headers->get('MCP-Protocol-Version');

        if (is_string($version) && $version !== '' && ! in_array($version, self::PROTOCOL_VERSIONS, true)) {
            return response()->json([
                'message' => 'Unsupported MCP-Protocol-Version.',
            ], 400);
        }

        return $next($request);
    }
}
