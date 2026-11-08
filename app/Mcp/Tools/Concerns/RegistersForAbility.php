<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\CmsMcpCredential;

trait RegistersForAbility
{
    private function credentialAllows(string $ability): bool
    {
        $credential = request()->attributes->get('cms_mcp_credential');

        return $credential instanceof CmsMcpCredential
            && $credential->isActive()
            && $credential->allows($ability);
    }
}
