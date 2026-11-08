<?php

namespace App\Mcp;

use App\Models\CmsMcpCredential;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;

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

    public function authorize(string $ability): CmsMcpCredential
    {
        $credential = $this->credential();

        if (! $credential->allows($ability)) {
            throw new AuthorizationException('This credential does not allow '.$ability.'.');
        }

        return $credential;
    }
}
