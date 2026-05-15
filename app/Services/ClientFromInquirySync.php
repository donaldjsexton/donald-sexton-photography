<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Inquiry;

class ClientFromInquirySync
{
    /**
     * Promote an Inquiry to a portal Client. Reuses an existing Client when one
     * is already linked, or when another inquiry from the same email has
     * already created one — so repeat inquiries from the same person attach to
     * their existing record instead of producing duplicates. Safe to call
     * repeatedly. The Client starts without a password — the admin must trigger
     * a portal invite separately.
     */
    public function syncFromInquiry(Inquiry $inquiry): Client
    {
        if ($inquiry->client_id !== null && ($existing = $inquiry->client()->first())) {
            return $existing;
        }

        $client = $this->findExistingClient($inquiry) ?? $this->createClientFromInquiry($inquiry);

        if ($inquiry->client_id !== $client->id) {
            $inquiry->client_id = $client->id;
            $inquiry->save();
        }

        return $client;
    }

    private function findExistingClient(Inquiry $inquiry): ?Client
    {
        $email = trim((string) $inquiry->email);
        if ($email === '') {
            return null;
        }

        return Client::query()->where('email', $email)->first();
    }

    private function createClientFromInquiry(Inquiry $inquiry): Client
    {
        [$firstName, $lastName] = $this->splitName($inquiry->primary_name);
        [$partnerFirst, $partnerLast] = $this->splitName($inquiry->partner_name);

        return Client::create([
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
