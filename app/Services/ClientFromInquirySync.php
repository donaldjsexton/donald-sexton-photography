<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Inquiry;

class ClientFromInquirySync
{
    /**
     * Promote an Inquiry to a portal Client. Returns the existing Client
     * if one is already linked, so this is safe to call repeatedly. The
     * Client starts without a password — the admin must trigger a portal
     * invite separately.
     */
    public function syncFromInquiry(Inquiry $inquiry): Client
    {
        if ($existing = $inquiry->client()->first()) {
            return $existing;
        }

        [$firstName, $lastName] = $this->splitName($inquiry->primary_name);
        [$partnerFirst, $partnerLast] = $this->splitName($inquiry->partner_name);

        return Client::create([
            'inquiry_id' => $inquiry->id,
            'first_name' => $firstName ?: 'Client',
            'last_name' => $lastName,
            'partner_first_name' => $partnerFirst,
            'partner_last_name' => $partnerLast,
            'email' => $inquiry->email,
            'phone' => $inquiry->phone,
            'city' => $inquiry->location_city,
            'country' => 'US',
        ]);
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function splitName(?string $fullName): array
    {
        $name = trim((string) $fullName);
        if ($name === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+/', $name, 2) ?: [$name];

        return [$parts[0] ?? null, $parts[1] ?? null];
    }
}
