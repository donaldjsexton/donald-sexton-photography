<?php

namespace App\Http\Middleware;

use App\Models\CmsMcpCredential;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCmsMcp
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');

        if (preg_match('/^Bearer\s+(\S+)\s*$/i', $header, $matches) !== 1) {
            return $this->unauthenticated();
        }

        $credential = CmsMcpCredential::query()
            ->where('token_hash', CmsMcpCredential::hashToken($matches[1]))
            ->first();

        if (! $credential instanceof CmsMcpCredential || ! $credential->isActive()) {
            return $this->unauthenticated();
        }

        $credential->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('cms_mcp_credential', $credential);

        return $next($request);
    }

    private function unauthenticated(): Response
    {
        return response()->json([
            'message' => 'Authentication required.',
        ], 401);
    }
}
