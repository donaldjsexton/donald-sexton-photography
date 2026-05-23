<?php

namespace App\Tenancy;

use App\Models\SiteDomain;

interface DomainVerifier
{
    /**
     * Confirm the domain's ownership token is present in DNS.
     */
    public function verify(SiteDomain $domain): bool;
}
