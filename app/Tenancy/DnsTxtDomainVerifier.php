<?php

namespace App\Tenancy;

use App\Models\SiteDomain;

class DnsTxtDomainVerifier implements DomainVerifier
{
    public function verify(SiteDomain $domain): bool
    {
        $records = @dns_get_record($domain->verificationRecordName(), DNS_TXT) ?: [];

        foreach ($records as $record) {
            if (isset($record['txt']) && trim((string) $record['txt']) === $domain->verification_token) {
                return true;
            }
        }

        return false;
    }
}
