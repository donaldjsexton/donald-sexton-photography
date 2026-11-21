<?php

namespace App\Mcp;

use App\Models\CmsMcpCredential;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Log;

class CmsMcpGate
{
    public function credential(): CmsMcpCredential
    {
        $credential = request()->attributes->get('cms_mcp_credential');

        if (! $credential instanceof CmsMcpCredential || ! $credential->isActive()) {
            throw new AuthenticationException('Authentication required.');
        }

        return $credential;
    }

    /**
     * Authorize a tool call and write the audit line. The log names the
     * credential and tool only. It never includes the token or arguments.
     */
    public function authorize(string $ability, ?string $tool = null): CmsMcpCredential
    {
        $credential = $this->credential();
        $allowed = $credential->allows($ability);

        Log::log($allowed ? 'info' : 'warning', 'cms_mcp.tool_call', [
            'credential_id' => $credential->id,
            'credential' => $credential->name,
            'tool' => $tool,
            'ability' => $ability,
            'allowed' => $allowed,
        ]);

        if (! $allowed) {
            throw new AuthorizationException('This credential does not allow '.$ability.'.');
        }

        return $credential;
    }
}
