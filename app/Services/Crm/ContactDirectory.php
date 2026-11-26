<?php

namespace App\Services\Crm;

use App\Models\Contact;
use App\Models\Suppression;
use Illuminate\Support\Carbon;

/**
 * Single entry point for creating/finding contacts and managing the
 * do-not-contact list. Email is the identity (lowercased, unique per site).
 */
class ContactDirectory
{
    /**
     * Find the contact for an email or create it. Existing contacts are only
     * filled in where fields are empty; type/stage/source are never overwritten.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function findOrCreate(string $email, string $source, array $attributes = []): Contact
    {
        $email = Contact::normalizeEmail($email);
        $contact = Contact::query()->where('email', $email)->first();

        if ($contact === null) {
            return Contact::create([
                'type' => 'prospect',
                'stage' => 'lead',
                'first_contact_at' => now(),
                'last_contact_at' => now(),
                ...$attributes,
                'email' => $email,
                'source' => $source,
            ]);
        }

        $fill = [];
        foreach (['name', 'partner_name', 'phone', 'shoot_type', 'event_date', 'notes'] as $field) {
            if (empty($contact->{$field}) && ! empty($attributes[$field])) {
                $fill[$field] = $attributes[$field];
            }
        }
        $fill['last_contact_at'] = now();
        $contact->forceFill($fill)->save();

        return $contact;
    }

    public function suppress(string $email, string $source, ?string $reason = null, ?Carbon $at = null): Suppression
    {
        $email = Contact::normalizeEmail($email);
        $contact = Contact::query()->where('email', $email)->first();

        return Suppression::query()->updateOrCreate(
            ['email' => $email],
            [
                'contact_id' => $contact?->id,
                'source' => $source,
                'reason' => $reason,
                'suppressed_at' => $at ?? now(),
            ],
        );
    }

    public function unsuppress(string $email): void
    {
        Suppression::query()->where('email', Contact::normalizeEmail($email))->delete();
    }

    public function isSuppressed(string $email): bool
    {
        return Suppression::query()->where('email', Contact::normalizeEmail($email))->exists();
    }
}
